<?php

namespace App\Services;

use App\Models\BatchMovement;
use App\Models\PackingBox;
use App\Models\PackingBoxSource;
use App\Models\PackingRun;
use App\Models\PoItem;
use App\Models\ProductionBatch;
use App\Models\ProductType;
use App\Models\PurchaseOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pembuatan box packing.
 *
 * Dua mode sesuai cara kerja di lapangan:
 *  - bulk    : "50 box @ 5 kg" sekaligus, beratnya bisa dikoreksi belakangan
 *  - per_box : timbang satu per satu, tiap box langsung terbit QR-nya
 *
 * Keduanya menghasilkan bentuk data yang sama: satu baris = satu box fisik,
 * lengkap dengan komposisi batch asalnya.
 */
class PackingService
{
    public function __construct(private readonly CodeGenerator $codes)
    {
    }

    /**
     * Buat sejumlah box sekaligus dari beberapa lot sumber.
     *
     * @param array<int, array{batch_id: int, kg: float}> $allocation lot dan jumlah kg yang diambil
     *
     * @return array{run: PackingRun, boxes: Collection<int, PackingBox>}
     */
    public function bulkGenerate(
        PurchaseOrder $po,
        ProductType $productType,
        array $allocation,
        int $boxCount,
        float $weightPerBox,
        array $meta = []
    ): array {
        if ($boxCount < 1) {
            throw new \RuntimeException('Jumlah box harus minimal satu.');
        }
        if ($weightPerBox <= 0) {
            throw new \RuntimeException('Berat per box harus lebih besar dari nol.');
        }

        $dibutuhkan = round($boxCount * $weightPerBox, 3);

        return DB::transaction(function () use ($po, $productType, $allocation, $boxCount, $weightPerBox, $dibutuhkan, $meta) {
            $lots = $this->lockAllocation($allocation, $productType);

            $tersedia = round($lots->sum('kg'), 3);
            if ($dibutuhkan > $tersedia + ProductionBatch::EPSILON) {
                throw new \RuntimeException(
                    "Butuh {$dibutuhkan} kg untuk {$boxCount} box, tetapi lot yang dipilih hanya menyediakan {$tersedia} kg."
                );
            }

            $run = PackingRun::create([
                'run_code'              => $this->codes->packingRun($meta['packing_date'] ?? today()),
                'purchase_order_id'     => $po->id,
                'po_item_id'            => $this->resolvePoItem($po, $productType)?->id,
                'product_type_id'       => $productType->id,
                'mode'                  => 'bulk',
                'planned_box_count'     => $boxCount,
                'planned_weight_per_box' => $weightPerBox,
                'packing_date'          => $meta['packing_date'] ?? today(),
                'operator_name'         => $meta['operator_name'] ?? null,
                'notes'                 => $meta['notes'] ?? null,
                'status'                => 'closed',
                'created_by'            => auth()->id(),
            ]);

            $boxes = collect();

            for ($i = 1; $i <= $boxCount; $i++) {
                // Box terakhir menyerap sisa pembulatan agar total box sama
                // persis dengan kg yang diambil dari lot.
                $berat = $i === $boxCount
                    ? round($dibutuhkan - ($weightPerBox * ($boxCount - 1)), 3)
                    : $weightPerBox;

                $boxes->push($this->createBox($run, $po, $productType, $lots, $berat, $i, $meta));
            }

            return ['run' => $run, 'boxes' => $boxes];
        });
    }

    /**
     * Timbang dan terbitkan satu box. Dipakai mode "timbang per box",
     * di mana operator menimbang lalu menekan Enter berulang kali.
     */
    public function weighSingleBox(
        PurchaseOrder $po,
        ProductType $productType,
        array $allocation,
        float $netWeightKg,
        array $meta = []
    ): PackingBox {
        if ($netWeightKg <= 0) {
            throw new \RuntimeException('Berat box harus lebih besar dari nol.');
        }

        return DB::transaction(function () use ($po, $productType, $allocation, $netWeightKg, $meta) {
            $lots = $this->lockAllocation($allocation, $productType, $netWeightKg);

            $run = $this->openRunFor($po, $productType, $meta);

            return $this->createBox(
                $run, $po, $productType, $lots, $netWeightKg,
                (int) $run->boxes()->max('sequence_no') + 1,
                $meta
            );
        });
    }

    /**
     * Koreksi berat sebuah box.
     *
     * Selisihnya ditulis sebagai baris `adjustment` di ledger batch asal,
     * bukan sekadar menimpa angka, supaya perubahan timbangan tetap terlihat.
     */
    public function adjustBoxWeight(PackingBox $box, float $netWeightKg, ?string $note = null): PackingBox
    {
        if ($netWeightKg <= 0) {
            throw new \RuntimeException('Berat box harus lebih besar dari nol.');
        }

        return DB::transaction(function () use ($box, $netWeightKg, $note) {
            $box   = PackingBox::where('id', $box->id)->lockForUpdate()->firstOrFail();
            $lama  = (float) $box->net_weight_kg;
            $delta = round($netWeightKg - $lama, 3);

            if (abs($delta) < ProductionBatch::EPSILON) {
                return $box;
            }

            $sources = $box->sources()->with('batch')->get();
            $total   = (float) $sources->sum('contributed_kg');
            $sisa    = $delta;

            foreach ($sources->values() as $i => $source) {
                $bagian = $i === $sources->count() - 1
                    ? round($sisa, 3)
                    : round($total > 0 ? $delta * ((float) $source->contributed_kg / $total) : 0, 3);
                $sisa = round($sisa - $bagian, 3);

                if (abs($bagian) < ProductionBatch::EPSILON) {
                    continue;
                }

                $batch = ProductionBatch::where('id', $source->batch_id)->lockForUpdate()->firstOrFail();

                // Berat box naik berarti batch berkurang lagi, dan sebaliknya.
                $batch->applyMovement(
                    BatchMovement::TYPE_ADJUSTMENT,
                    $bagian > 0 ? 'out' : 'in',
                    abs($bagian),
                    [
                        'packing_box_id' => $box->id,
                        'note'           => $note ?: "Koreksi berat {$box->box_code}: {$lama} kg -> {$netWeightKg} kg",
                    ]
                );

                $source->update([
                    'contributed_kg' => round((float) $source->contributed_kg + $bagian, 3),
                ]);
            }

            $box->update([
                'net_weight_kg' => $netWeightKg,
                'weighed_at'    => now(),
                'updated_by'    => auth()->id(),
            ]);

            $this->refreshSourcePercentages($box->fresh());

            return $box->fresh();
        });
    }

    /**
     * Batalkan sebuah box: isinya dikembalikan ke batch asal lewat baris
     * reversal. Kode box tidak pernah dipakai ulang.
     */
    public function voidBox(PackingBox $box, string $reason): void
    {
        DB::transaction(function () use ($box, $reason) {
            $box = PackingBox::where('id', $box->id)->lockForUpdate()->firstOrFail();

            if ($box->status === 'voided') {
                throw new \RuntimeException('Box ini sudah dibatalkan sebelumnya.');
            }

            foreach ($box->sources as $source) {
                $batch = ProductionBatch::where('id', $source->batch_id)->lockForUpdate()->first();

                if ($batch && (float) $source->contributed_kg > 0) {
                    $batch->applyMovement(BatchMovement::TYPE_REVERSAL, 'in', (float) $source->contributed_kg, [
                        'packing_box_id' => $box->id,
                        'note'           => "Pembatalan box {$box->box_code}: {$reason}",
                    ]);
                }
            }

            $box->update(['status' => 'voided', 'notes' => $reason, 'updated_by' => auth()->id()]);
        });
    }

    // ── Bagian dalam ──────────────────────────────────────────────

    /**
     * Kunci lot sumber dan pastikan isinya cukup.
     *
     * @return Collection<int, array{batch: ProductionBatch, kg: float}>
     */
    private function lockAllocation(array $allocation, ProductType $productType, ?float $maxTotal = null): Collection
    {
        $rows = collect($allocation)
            ->filter(fn ($row) => (float) ($row['kg'] ?? 0) > 0)
            ->values();

        if ($rows->isEmpty()) {
            throw new \RuntimeException('Pilih minimal satu lot sumber beserta jumlah kg yang diambil.');
        }

        return $rows->map(function (array $row) use ($productType) {
            $batch = ProductionBatch::where('id', $row['batch_id'])->lockForUpdate()->first();

            if (!$batch) {
                throw new \RuntimeException("Lot dengan id {$row['batch_id']} tidak ditemukan.");
            }
            if ($batch->status === 'voided') {
                throw new \RuntimeException("Lot {$batch->batch_number} sudah dibatalkan.");
            }
            if ($batch->product_type_id && (int) $batch->product_type_id !== (int) $productType->id) {
                throw new \RuntimeException(
                    "Lot {$batch->batch_number} berisi {$batch->productType?->name}, bukan {$productType->name}."
                );
            }

            $kg = round((float) $row['kg'], 3);

            if ($kg > (float) $batch->current_weight_kg + ProductionBatch::EPSILON) {
                throw new \RuntimeException(
                    "Lot {$batch->batch_number} hanya tersisa {$batch->current_weight_kg} kg, tidak cukup untuk {$kg} kg."
                );
            }

            return ['batch' => $batch, 'kg' => $kg];
        });
    }

    private function createBox(
        PackingRun $run,
        PurchaseOrder $po,
        ProductType $productType,
        Collection $lots,
        float $netWeightKg,
        int $sequence,
        array $meta
    ): PackingBox {
        $total   = round($lots->sum('kg'), 3);
        $dominan = $lots->sortByDesc('kg')->first()['batch'];

        $box = PackingBox::create([
            'box_code'          => $this->codes->boxCode($po, $productType),
            'qr_code'           => $this->codes->qrToken('packing_boxes'),
            'packing_run_id'    => $run->id,
            'purchase_order_id' => $po->id,
            'po_item_id'        => $run->po_item_id,
            'product_type_id'   => $productType->id,
            'primary_batch_id'  => $dominan->id,
            'sequence_no'       => $sequence,
            'net_weight_kg'     => $netWeightKg,
            'is_mixed_batch'    => $lots->count() > 1,
            'status'            => 'packed',
            'packing_date'      => $run->packing_date,
            'weighed_at'        => now(),
            'operator_name'     => $meta['operator_name'] ?? $run->operator_name,
            'created_by'        => auth()->id(),
        ]);

        // Isi box diambil dari tiap lot secara proporsional terhadap alokasi.
        $sisa  = $netWeightKg;
        $count = $lots->count();

        foreach ($lots->values() as $i => $lot) {
            $bagian = $i === $count - 1
                ? round($sisa, 3)
                : round($total > 0 ? $netWeightKg * ($lot['kg'] / $total) : 0, 3);
            $sisa = round($sisa - $bagian, 3);

            if ($bagian <= 0) {
                continue;
            }

            $batch = ProductionBatch::where('id', $lot['batch']->id)->lockForUpdate()->firstOrFail();

            $batch->applyMovement(BatchMovement::TYPE_PACK, 'out', $bagian, [
                'packing_box_id' => $box->id,
                'occurred_at'    => $run->packing_date,
                'note'           => "Masuk box {$box->box_code}",
            ]);

            $this->recordSources($box, $batch, $bagian, $netWeightKg);
        }

        // Ditandai campuran berdasarkan batch induk yang benar-benar masuk,
        // bukan jumlah lot yang dipilih: satu lot gabungan sudah membuat
        // isinya berasal dari beberapa induk.
        $box->update([
            'is_mixed_batch' => $box->sources()->distinct()->count('root_batch_id') > 1,
        ]);

        return $box->fresh('sources');
    }

    /**
     * Catat asal isi box, satu baris per batch induk.
     *
     * Sebuah lot akhir bisa berasal dari banyak batch induk sekaligus (sesi yang
     * menggabungkan beberapa supplier). Menyimpan hanya induk dominan membuat
     * rekap "bahan baku dari mana saja" melaporkan satu supplier untuk seluruh
     * box, jadi seluruh asal ditulis di sini berikut porsinya masing-masing.
     */
    private function recordSources(PackingBox $box, ProductionBatch $batch, float $kg, float $netWeightKg): void
    {
        $paths = $batch->rootLots($kg);

        if ($paths->isEmpty()) {
            // Lot tanpa jejak tahap (mis. dipacking langsung dari penerimaan).
            $paths = collect([['root' => $batch, 'kg' => $kg]]);
        }

        // Lot per wadah dikelompokkan kembali ke batch induk penerimaannya,
        // supaya rekap tidak pecah menjadi puluhan baris per keranjang.
        $perInduk = [];

        foreach ($paths as $path) {
            $root  = $path['root'];
            $induk = $root->root_batch_id ?: $root->id;

            if (!isset($perInduk[$induk])) {
                $perInduk[$induk] = ['batch' => $root, 'kg' => 0.0];
            }
            $perInduk[$induk]['kg'] += (float) $path['kg'];
        }

        // Sisa pembulatan dibebankan ke penyumbang terbesar agar total baris
        // selalu sama persis dengan berat yang diambil.
        $rows    = collect($perInduk)->sortByDesc('kg');
        $terpakai = 0.0;
        $terakhir = $rows->count() - 1;

        foreach ($rows->values() as $i => $row) {
            $bagian = $i === $terakhir ? round($kg - $terpakai, 3) : round($row['kg'], 3);
            $terpakai = round($terpakai + $bagian, 3);

            if ($bagian <= 0) {
                continue;
            }

            $induk = ProductionBatch::find($row['batch']->root_batch_id ?: $row['batch']->id);

            PackingBoxSource::create([
                'packing_box_id'   => $box->id,
                'batch_id'         => $batch->id,
                'root_batch_id'    => $induk?->id,
                'supplier_id'      => $induk?->supplier_id ?? $row['batch']->supplier_id,
                'contributed_kg'   => $bagian,
                'contribution_pct' => $netWeightKg > 0 ? round(($bagian / $netWeightKg) * 100, 3) : 0,
            ]);
        }
    }

    /** Sesi packing terbuka untuk mode timbang-per-box, dibuat bila belum ada. */
    private function openRunFor(PurchaseOrder $po, ProductType $productType, array $meta): PackingRun
    {
        $tanggal = $meta['packing_date'] ?? today();

        $run = PackingRun::where('purchase_order_id', $po->id)
            ->where('product_type_id', $productType->id)
            ->where('mode', 'per_box')
            ->where('status', 'open')
            ->whereDate('packing_date', $tanggal)
            ->first();

        return $run ?: PackingRun::create([
            'run_code'          => $this->codes->packingRun($tanggal),
            'purchase_order_id' => $po->id,
            'po_item_id'        => $this->resolvePoItem($po, $productType)?->id,
            'product_type_id'   => $productType->id,
            'mode'              => 'per_box',
            'packing_date'      => $tanggal,
            'operator_name'     => $meta['operator_name'] ?? null,
            'status'            => 'open',
            'created_by'        => auth()->id(),
        ]);
    }

    private function resolvePoItem(PurchaseOrder $po, ProductType $productType): ?PoItem
    {
        return PoItem::where('purchase_order_id', $po->id)
            ->where('product_type_id', $productType->id)
            ->first();
    }

    private function refreshSourcePercentages(PackingBox $box): void
    {
        $net = (float) $box->net_weight_kg;

        foreach ($box->sources as $source) {
            $source->update([
                'contribution_pct' => $net > 0 ? round(((float) $source->contributed_kg / $net) * 100, 3) : 0,
            ]);
        }
    }
}
