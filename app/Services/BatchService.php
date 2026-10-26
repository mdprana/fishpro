<?php

namespace App\Services;

use App\Models\BatchMovement;
use App\Models\ProductionBatch;
use App\Models\Receiving;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pembuatan dan konsumsi batch.
 *
 * Semua perubahan berat melewati ledger (batch_movements); tidak ada satu pun
 * jalur yang menulis current_weight_kg secara langsung.
 */
class BatchService
{
    public function __construct(
        private readonly CodeGenerator $codes,
        private readonly StockService $stock,
    ) {
    }

    /**
     * Terbitkan Batch Number Induk (Parent Lot) dari sebuah penerimaan,
     * beserta lot per wadah bila kiriman dipecah per keranjang/palet.
     *
     * Ketika dipecah, berat dibagi rata dan sisa pembulatan ditambahkan ke
     * wadah terakhir supaya total anak selalu sama persis dengan berat kiriman.
     *
     * @return Collection<int, ProductionBatch> batch yang bisa dipakai produksi
     */
    public function createFromReceiving(Receiving $receiving): Collection
    {
        $receiving->loadMissing(['supplier', 'subgroup']);

        if (!$receiving->supplier) {
            throw new \RuntimeException('Penerimaan harus punya supplier untuk membentuk nomor batch.');
        }

        return DB::transaction(function () use ($receiving) {
            $parent = $this->createParentBatch($receiving);

            $containerCount = (int) $receiving->container_count;
            $split          = (bool) $receiving->split_per_container && $containerCount > 1;

            $usable = $split
                ? $this->splitIntoContainers($parent, $receiving, $containerCount)
                : collect([$parent]);

            $this->stock->recomputeSubgroup((int) $receiving->material_subgroup_id);

            return $usable;
        });
    }

    private function createParentBatch(Receiving $receiving): ProductionBatch
    {
        $weight = (float) $receiving->weight_kg;

        $batch = ProductionBatch::create([
            'batch_number'         => $this->codes->parentLot(
                $receiving->supplier, $receiving->subgroup, $receiving->received_date
            ),
            'qr_code'              => $this->codes->qrToken('production_batches'),
            'parent_batch_id'      => null,
            'root_batch_id'        => null,   // diisi ke dirinya sendiri setelah id ada
            'level'                => 0,
            'process_stage_id'     => null,   // belum masuk tahap mana pun
            'origin'               => 'receiving',
            'purchase_order_id'    => $receiving->purchase_order_id,
            'supplier_id'          => $receiving->supplier_id,
            'material_subgroup_id' => $receiving->material_subgroup_id,
            'receiving_id'         => $receiving->id,
            'content_type'         => 'raw',
            'initial_weight_kg'    => $weight,
            'current_weight_kg'    => 0,      // diisi lewat ledger di bawah
            'container_type'       => $receiving->container_type,
            'container_count'      => $receiving->container_count,
            'status'               => 'available',
            'produced_at'          => $receiving->received_date->copy()->setTimeFrom(now()),
            'effective_date'       => $receiving->received_date,  // = tanggal bongkar
            'notes'                => $receiving->notes,
            'created_by'           => $receiving->created_by,
        ]);

        $batch->update(['root_batch_id' => $batch->id]);

        $batch->applyMovement(BatchMovement::TYPE_RECEIPT, 'in', $weight, [
            'receiving_id' => $receiving->id,
            'occurred_at'  => $receiving->received_date,
            'note'         => 'Penerimaan bahan baku dari ' . $receiving->supplier->display_name,
            'created_by'   => $receiving->created_by,
        ]);

        return $batch;
    }

    /**
     * Pecah batch induk menjadi satu lot per wadah fisik, supaya tiap
     * keranjang/palet punya label QR sendiri yang bisa di-scan di lantai produksi.
     *
     * @return Collection<int, ProductionBatch>
     */
    private function splitIntoContainers(ProductionBatch $parent, Receiving $receiving, int $count): Collection
    {
        $total   = (float) $receiving->weight_kg;
        $perUnit = floor(($total / $count) * 1000) / 1000; // 3 desimal, dibulatkan ke bawah
        $batches = collect();

        for ($i = 1; $i <= $count; $i++) {
            // Wadah terakhir menyerap sisa pembulatan agar totalnya persis.
            $weight = $i === $count
                ? round($total - ($perUnit * ($count - 1)), 3)
                : $perUnit;

            if ($weight <= 0) {
                continue;
            }

            $child = ProductionBatch::create([
                'batch_number'         => $this->codes->containerLot($parent->batch_number, $i),
                'qr_code'              => $this->codes->qrToken('production_batches'),
                'parent_batch_id'      => $parent->id,
                'root_batch_id'        => $parent->id,
                'level'                => 1,
                'process_stage_id'     => null,
                'origin'               => 'receiving',
                'purchase_order_id'    => $parent->purchase_order_id,
                'supplier_id'          => $parent->supplier_id,
                'material_subgroup_id' => $parent->material_subgroup_id,
                'receiving_id'         => $receiving->id,
                'content_type'         => 'raw',
                'initial_weight_kg'    => $weight,
                'current_weight_kg'    => 0,
                'container_type'       => $receiving->container_type,
                'container_count'      => 1,
                'status'               => 'available',
                'produced_at'          => $parent->produced_at,
                'effective_date'       => $parent->effective_date,
                'created_by'           => $receiving->created_by,
            ]);

            $child->applyMovement(BatchMovement::TYPE_STAGE_PRODUCE, 'in', $weight, [
                'receiving_id' => $receiving->id,
                'occurred_at'  => $receiving->received_date,
                'note'         => "Pecah wadah {$i}/{$count} dari {$parent->batch_number}",
                'created_by'   => $receiving->created_by,
            ]);

            $batches->push($child);
        }

        // Isi induk dipindahkan seluruhnya ke anak-anaknya; induk tinggal
        // menjadi simpul silsilah, bukan stok yang bisa dipakai lagi.
        $parent->refresh()->applyMovement(BatchMovement::TYPE_STAGE_CONSUME, 'out', (float) $receiving->weight_kg, [
            'receiving_id' => $receiving->id,
            'occurred_at'  => $receiving->received_date,
            'note'         => "Dipecah menjadi {$batches->count()} wadah",
            'created_by'   => $receiving->created_by,
        ]);

        return $batches;
    }

    /**
     * Batalkan seluruh batch sebuah penerimaan.
     *
     * Ditolak bila ada isi yang sudah dipakai produksi, sebab membatalkan penerimaan
     * yang sudah diproses akan membuat hasil produksi kehilangan asal-usulnya.
     */
    public function voidReceivingBatches(Receiving $receiving, ?string $reason = null): void
    {
        DB::transaction(function () use ($receiving, $reason) {
            $batches = ProductionBatch::where('receiving_id', $receiving->id)
                ->notVoided()->lockForUpdate()->get();

            foreach ($batches as $batch) {
                $terpakai = $batch->level === 0 && $batches->count() > 1
                    ? 0                       // induk memang sengaja dikosongkan saat dipecah
                    : $batch->consumed_kg;

                if ($terpakai > ProductionBatch::EPSILON) {
                    throw new \RuntimeException(
                        "Batch {$batch->batch_number} sudah dipakai produksi sebanyak {$terpakai} kg, "
                        . "sehingga penerimaan ini tidak bisa dibatalkan."
                    );
                }
            }

            foreach ($batches as $batch) {
                if ((float) $batch->current_weight_kg > ProductionBatch::EPSILON) {
                    $batch->applyMovement(BatchMovement::TYPE_REVERSAL, 'out', (float) $batch->current_weight_kg, [
                        'receiving_id' => $receiving->id,
                        'note'         => $reason ?: 'Penerimaan dibatalkan',
                    ]);
                }

                $batch->update(['status' => 'voided', 'updated_by' => auth()->id()]);
            }

            $this->stock->recomputeSubgroup((int) $receiving->material_subgroup_id);
        });
    }
}
