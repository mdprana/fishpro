<?php

namespace App\Services;

use App\Models\BatchMovement;
use App\Models\BatchStageInput;
use App\Models\BatchStageLink;
use App\Models\BatchStageOutput;
use App\Models\BatchStageRecord;
use App\Models\ProcessStage;
use App\Models\ProductionBatch;
use App\Models\YieldStandard;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pencatatan satu sesi tahap produksi (Potong / Trimming / Potong Steak).
 *
 * Satu pemanggilan = satu transaksi: batch input dikunci dan dikurangi,
 * batch output diterbitkan, limbah dicatat, dan kontribusi tiap input ke tiap
 * output dihitung supaya penelusuran balik tetap mungkin walau beberapa batch
 * digabung dalam satu sesi.
 */
class StageService
{
    public function __construct(
        private readonly CodeGenerator $codes,
        private readonly StockService $stock,
    ) {
    }

    /**
     * @param array $data {
     *   purchase_order_id, process_date, start_time?, end_time?, operator_name?,
     *   shift?, line_name?, notes?,
     *   inputs:  [{batch_id, input_weight_kg}],
     *   outputs: [{output_type: utama|sampingan, product_type_id?, output_weight_kg,
     *              container_type?, container_count?, notes?}],
     *   wastes:  [{waste_category, output_weight_kg, notes?}],
     *   allocations?: [outputIndex => [batch_id => kg]]   // null = proporsional
     * }
     *
     * @return array{record: BatchStageRecord, alerts: array<int, array>}
     */
    public function record(ProcessStage $stage, array $data): array
    {
        return DB::transaction(function () use ($stage, $data) {
            $inputs = $this->lockAndValidateInputs($data['inputs'] ?? []);

            $totalInput  = round($inputs->sum('kg'), 3);
            $outputsData = collect($data['outputs'] ?? [])->filter(fn ($o) => (float) ($o['output_weight_kg'] ?? 0) > 0)->values();
            $wastesData  = collect($data['wastes']  ?? [])->filter(fn ($w) => (float) ($w['output_weight_kg'] ?? 0) > 0)->values();

            $totalOutput = round($outputsData->sum(fn ($o) => (float) $o['output_weight_kg']), 3);
            $totalWaste  = round($wastesData->sum(fn ($w) => (float) $w['output_weight_kg']), 3);

            if ($totalOutput + $totalWaste > $totalInput + ProductionBatch::EPSILON) {
                throw new \RuntimeException(sprintf(
                    'Total hasil (%s kg) dan limbah (%s kg) melebihi bahan yang masuk (%s kg).',
                    $totalOutput, $totalWaste, $totalInput
                ));
            }

            $record = BatchStageRecord::create([
                'record_code'       => $this->codes->stageRecord($stage, $data['process_date']),
                'process_stage_id'  => $stage->id,
                'purchase_order_id' => $data['purchase_order_id'],
                'process_date'      => $data['process_date'],
                'start_time'        => $data['start_time']    ?? null,
                'end_time'          => $data['end_time']      ?? null,
                'operator_name'     => $data['operator_name'] ?? null,
                'shift'             => $data['shift']         ?? null,
                'line_name'         => $data['line_name']     ?? null,
                'notes'             => $data['notes']         ?? null,
                'status'            => 'completed',
                'created_by'        => auth()->id(),
            ]);

            $this->consumeInputs($record, $inputs);

            $outputBatches = $this->produceOutputs($record, $stage, $inputs, $outputsData, $data);
            $this->recordWastes($record, $inputs, $wastesData, $totalInput);

            $this->refreshTotals($record, $totalInput, $totalOutput, $totalWaste, $outputsData);
            $this->refreshRawStock($inputs);

            return [
                'record'  => $record->fresh(['outputs', 'inputs']),
                'batches' => $outputBatches,
                'alerts'  => $this->checkStandards($record->fresh(), $inputs),
            ];
        });
    }

    /**
     * Kunci tiap batch input lalu pastikan isinya cukup.
     * lockForUpdate mencegah dua operator menghabiskan keranjang yang sama.
     *
     * @return Collection<int, array{batch: ProductionBatch, kg: float}>
     */
    private function lockAndValidateInputs(array $rows): Collection
    {
        if (empty($rows)) {
            throw new \RuntimeException('Minimal satu batch bahan harus dipilih sebagai input.');
        }

        // Batch yang sama disebut dua kali digabung, bukan ditolak, karena operator
        // kadang men-scan ulang keranjang yang sama.
        $merged = [];
        foreach ($rows as $row) {
            $id = (int) $row['batch_id'];
            $merged[$id] = ($merged[$id] ?? 0) + (float) $row['input_weight_kg'];
        }

        return collect($merged)->map(function (float $kg, int $batchId) {
            $batch = ProductionBatch::where('id', $batchId)->lockForUpdate()->first();

            if (!$batch) {
                throw new \RuntimeException("Batch dengan id {$batchId} tidak ditemukan.");
            }

            if ($batch->status === 'voided') {
                throw new \RuntimeException("Batch {$batch->batch_number} sudah dibatalkan dan tidak bisa diproses.");
            }

            if ($kg <= 0) {
                throw new \RuntimeException("Berat yang diambil dari {$batch->batch_number} harus lebih dari nol.");
            }

            if ($kg > (float) $batch->current_weight_kg + ProductionBatch::EPSILON) {
                throw new \RuntimeException(
                    "Batch {$batch->batch_number} hanya tersisa {$batch->current_weight_kg} kg, "
                    . "tidak cukup untuk mengambil {$kg} kg."
                );
            }

            return ['batch' => $batch, 'kg' => round($kg, 3)];
        })->values();
    }

    private function consumeInputs(BatchStageRecord $record, Collection $inputs): void
    {
        foreach ($inputs as $input) {
            BatchStageInput::create([
                'batch_stage_record_id' => $record->id,
                'batch_id'              => $input['batch']->id,
                'input_weight_kg'       => $input['kg'],
            ]);

            $input['batch']->applyMovement(BatchMovement::TYPE_STAGE_CONSUME, 'out', $input['kg'], [
                'batch_stage_record_id' => $record->id,
                'occurred_at'           => $record->process_date,
                'note'                  => "Dipakai {$record->stage->name} ({$record->record_code})",
            ]);
        }
    }

    /**
     * Terbitkan batch hasil dan catat kontribusi tiap input ke tiap output.
     *
     * @return Collection<int, ProductionBatch>
     */
    private function produceOutputs(
        BatchStageRecord $record,
        ProcessStage $stage,
        Collection $inputs,
        Collection $outputsData,
        array $data
    ): Collection {
        $totalInput = round($inputs->sum('kg'), 3);
        $multi      = $inputs->count() > 1;
        $produced   = collect();

        foreach ($outputsData as $index => $out) {
            $weight = round((float) $out['output_weight_kg'], 3);

            $batch = $this->createOutputBatch($record, $stage, $inputs, $out, $weight, $multi);

            $outputRow = BatchStageOutput::create([
                'batch_stage_record_id' => $record->id,
                'output_batch_id'       => $batch->id,
                'output_type'           => $out['output_type'] ?? 'utama',
                'product_type_id'       => $out['product_type_id'] ?? null,
                'output_weight_kg'      => $weight,
                'container_type'        => $out['container_type'] ?? null,
                'container_count'       => (int) ($out['container_count'] ?? 1) ?: 1,
                'notes'                 => $out['notes'] ?? null,
            ]);

            $batch->applyMovement(BatchMovement::TYPE_STAGE_PRODUCE, 'in', $weight, [
                'batch_stage_record_id' => $record->id,
                'occurred_at'           => $record->process_date,
                'note'                  => "Hasil {$stage->name} ({$record->record_code})",
            ]);

            $this->linkSources($record, $outputRow, $batch, $inputs, $weight, $totalInput, $data['allocations'][$index] ?? null);

            $produced->push($batch);
        }

        return $produced;
    }

    private function createOutputBatch(
        BatchStageRecord $record,
        ProcessStage $stage,
        Collection $inputs,
        array $out,
        float $weight,
        bool $multi
    ): ProductionBatch {
        // Induk untuk penomoran & tampilan: batch penyumbang terbesar.
        $dominan = $inputs->sortByDesc('kg')->first()['batch'];

        // Nomor menempel pada induk bila tunggal, supaya rantai asal terbaca
        // langsung dari nomornya. Untuk gabungan tidak ada satu induk yang
        // adil dijadikan awalan, jadi memakai penomoran MIX tersendiri.
        $number = $multi
            ? $this->codes->mixedLot($stage, $record->process_date)
            : $this->codes->stageLot($dominan->batch_number, $stage, $this->nextStageSequence($dominan, $stage));

        return ProductionBatch::create([
            'batch_number'         => $number,
            'qr_code'              => $this->codes->qrToken('production_batches'),
            'parent_batch_id'      => $dominan->id,
            'root_batch_id'        => $multi ? null : $dominan->root_batch_id,
            'level'                => (int) $dominan->level + 1,
            'is_multi_source'      => $multi,
            'process_stage_id'     => $stage->id,
            'origin'               => 'stage',
            'purchase_order_id'    => $record->purchase_order_id,
            'supplier_id'          => $multi ? null : $dominan->supplier_id,
            'material_subgroup_id' => $dominan->material_subgroup_id,
            'product_type_id'      => $out['product_type_id'] ?? null,
            'batch_stage_record_id' => $record->id,
            'content_type'         => ($out['output_type'] ?? 'utama') === 'sampingan' ? 'byproduct'
                                      : ($stage->is_final_stage ? 'finished' : 'wip'),
            'initial_weight_kg'    => $weight,
            'current_weight_kg'    => 0,          // diisi lewat ledger
            'container_type'       => $out['container_type'] ?? null,
            'container_count'      => (int) ($out['container_count'] ?? 1) ?: 1,
            'status'               => 'available',
            'produced_at'          => now(),
            'effective_date'       => $record->process_date, // tgl potong / trimming / steak
            'notes'                => $out['notes'] ?? null,
            'created_by'           => auth()->id(),
        ]);
    }

    /** Urutan lot tahap ini di bawah satu induk (CT01, CT02, …). */
    private function nextStageSequence(ProductionBatch $parent, ProcessStage $stage): int
    {
        return ProductionBatch::where('parent_batch_id', $parent->id)
            ->where('process_stage_id', $stage->id)->count() + 1;
    }

    /**
     * Catat berapa kg tiap batch input menyumbang ke batch output ini.
     *
     * Default proporsional terhadap porsi input. Operator bisa menimpanya
     * bila di lapangan pemisahannya memang tidak merata.
     */
    private function linkSources(
        BatchStageRecord $record,
        BatchStageOutput $outputRow,
        ProductionBatch $outputBatch,
        Collection $inputs,
        float $outputWeight,
        float $totalInput,
        ?array $manual
    ): void {
        $manualTotal = $manual ? round(array_sum(array_map('floatval', $manual)), 3) : null;
        $useManual   = $manual && abs($manualTotal - $outputWeight) <= 0.01;

        $sisa  = $outputWeight;
        $count = $inputs->count();

        foreach ($inputs->values() as $i => $input) {
            $batchId = $input['batch']->id;

            if ($useManual) {
                $kg = round((float) ($manual[$batchId] ?? 0), 3);
            } elseif ($i === $count - 1) {
                // Baris terakhir menyerap sisa pembulatan agar totalnya persis.
                $kg = round($sisa, 3);
            } else {
                $kg = round($totalInput > 0 ? $outputWeight * ($input['kg'] / $totalInput) : 0, 3);
            }

            $sisa = round($sisa - $kg, 3);

            if ($kg <= 0) {
                continue;
            }

            BatchStageLink::create([
                'batch_stage_record_id' => $record->id,
                'batch_stage_output_id' => $outputRow->id,
                'source_batch_id'       => $batchId,
                'output_batch_id'       => $outputBatch->id,
                'contributed_kg'        => $kg,
                'contribution_pct'      => $outputWeight > 0 ? round(($kg / $outputWeight) * 100, 3) : 0,
                'allocation_method'     => $useManual ? 'manual' : 'proportional',
            ]);
        }
    }

    /**
     * Limbah tidak menjadi batch. Ia hanya keluar dari batch input,
     * dibebankan proporsional ke tiap input.
     */
    private function recordWastes(BatchStageRecord $record, Collection $inputs, Collection $wastes, float $totalInput): void
    {
        foreach ($wastes as $waste) {
            $weight = round((float) $waste['output_weight_kg'], 3);

            BatchStageOutput::create([
                'batch_stage_record_id' => $record->id,
                'output_batch_id'       => null,
                'output_type'           => 'waste',
                'waste_category'        => $waste['waste_category'] ?? 'lainnya',
                'output_weight_kg'      => $weight,
                'notes'                 => $waste['notes'] ?? null,
            ]);
        }
    }

    private function refreshTotals(
        BatchStageRecord $record,
        float $totalInput,
        float $totalOutput,
        float $totalWaste,
        Collection $outputsData
    ): void {
        $utama = round(
            $outputsData->where('output_type', 'utama')->sum(fn ($o) => (float) $o['output_weight_kg']),
            3
        );

        $record->update([
            'total_input_kg'  => $totalInput,
            'total_output_kg' => $totalOutput,
            'total_waste_kg'  => $totalWaste,
            // Yield diukur dari hasil utama. Sampingan dan limbah bukan
            // ukuran keberhasilan tahap ini.
            'yield_pct'       => $totalInput > 0 ? round(($utama / $totalInput) * 100, 2) : 0,
        ]);
    }

    private function refreshRawStock(Collection $inputs): void
    {
        $inputs->pluck('batch.material_subgroup_id')->filter()->unique()
            ->each(fn ($id) => $this->stock->recomputeSubgroup((int) $id));
    }

    /**
     * Bandingkan yield sesi ini dengan standar toleransi pabrik.
     *
     * @return array<int, array{level: string, message: string, standard: YieldStandard}>
     */
    private function checkStandards(BatchStageRecord $record, Collection $inputs): array
    {
        $alerts    = [];
        $yieldPct  = (float) $record->yield_pct;
        $subgroups = $inputs->pluck('batch.material_subgroup_id')->filter()->unique();

        foreach ($subgroups as $subgroupId) {
            $standard = YieldStandard::resolve(
                (int) $subgroupId,
                (int) $record->process_stage_id,
                $record->process_date
            );

            if (!$standard) {
                continue;
            }

            $hasil = $standard->evaluate($yieldPct);

            if ($hasil['level'] !== 'ok') {
                $alerts[] = $hasil + ['standard' => $standard];
            }
        }

        return $alerts;
    }

    /**
     * Batalkan sebuah sesi: kembalikan bahan ke batch asal, batalkan batch
     * hasilnya, dan tulis semuanya sebagai baris reversal. Tidak ada yang dihapus.
     */
    public function void(BatchStageRecord $record, string $reason): void
    {
        DB::transaction(function () use ($record, $reason) {
            if ($record->is_voided) {
                throw new \RuntimeException('Sesi ini sudah dibatalkan sebelumnya.');
            }

            $record->load(['inputs.batch', 'outputs.outputBatch']);

            // Hasil yang sudah dipakai tahap berikutnya tidak boleh ditarik,
            // karena produk turunannya akan kehilangan asal-usul.
            foreach ($record->outputs as $output) {
                $batch = $output->outputBatch;
                if ($batch && $batch->consumed_kg > ProductionBatch::EPSILON) {
                    throw new \RuntimeException(
                        "Hasil {$batch->batch_number} sudah diproses lebih lanjut, sesi ini tidak bisa dibatalkan."
                    );
                }
            }

            foreach ($record->outputs as $output) {
                $batch = $output->outputBatch;
                if (!$batch) {
                    continue;
                }

                $batch = ProductionBatch::where('id', $batch->id)->lockForUpdate()->first();

                if ((float) $batch->current_weight_kg > ProductionBatch::EPSILON) {
                    $batch->applyMovement(BatchMovement::TYPE_REVERSAL, 'out', (float) $batch->current_weight_kg, [
                        'batch_stage_record_id' => $record->id,
                        'note'                  => "Pembatalan {$record->record_code}: {$reason}",
                    ]);
                }

                $batch->update(['status' => 'voided', 'updated_by' => auth()->id()]);
            }

            foreach ($record->inputs as $input) {
                $batch = ProductionBatch::where('id', $input->batch_id)->lockForUpdate()->first();

                $batch->applyMovement(BatchMovement::TYPE_REVERSAL, 'in', (float) $input->input_weight_kg, [
                    'batch_stage_record_id' => $record->id,
                    'note'                  => "Pengembalian atas pembatalan {$record->record_code}",
                ]);
            }

            $record->update([
                'status'      => 'voided',
                'voided_by'   => auth()->id(),
                'voided_at'   => now(),
                'void_reason' => $reason,
            ]);

            $this->refreshRawStock(
                $record->inputs->map(fn ($i) => ['batch' => $i->batch, 'kg' => (float) $i->input_weight_kg])
            );
        });
    }
}
