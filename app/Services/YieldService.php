<?php

namespace App\Services;

use App\Models\BatchStageRecord;
use App\Models\PackingBox;
use App\Models\ProcessStage;
use App\Models\PurchaseOrder;
use App\Models\Receiving;
use App\Models\YieldStandard;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Perhitungan yield per tahap dan recovery keseluruhan.
 *
 * Recovery = Total Berat Packing / Total Berat Bahan Baku Diterima x 100%.
 */
class YieldService
{
    /** Ringkasan recovery pabrik pada rentang tanggal tertentu. */
    public function summary(?string $from = null, ?string $to = null): array
    {
        $bahan = Receiving::query()
            ->when($from, fn ($q) => $q->whereDate('received_date', '>=', $from))
            ->when($to,   fn ($q) => $q->whereDate('received_date', '<=', $to))
            ->sum('weight_kg');

        $packing = PackingBox::active()
            ->when($from, fn ($q) => $q->whereDate('packing_date', '>=', $from))
            ->when($to,   fn ($q) => $q->whereDate('packing_date', '<=', $to))
            ->sum('net_weight_kg');

        $bahan   = round((float) $bahan, 2);
        $packing = round((float) $packing, 2);

        return [
            'bahan_kg'    => $bahan,
            'packing_kg'  => $packing,
            'susut_kg'    => round($bahan - $packing, 2),
            'recovery_pct' => $bahan > 0 ? round(($packing / $bahan) * 100, 2) : 0.0,
        ];
    }

    /** Yield per tahap: berapa yang masuk, jadi hasil utama, dan jadi limbah. */
    public function perStage(?string $from = null, ?string $to = null): Collection
    {
        return ProcessStage::active()->ordered()->get()->map(function (ProcessStage $stage) use ($from, $to) {
            $records = BatchStageRecord::completed()
                ->where('process_stage_id', $stage->id)
                ->when($from, fn ($q) => $q->whereDate('process_date', '>=', $from))
                ->when($to,   fn ($q) => $q->whereDate('process_date', '<=', $to));

            $input  = (float) (clone $records)->sum('total_input_kg');
            $output = (float) (clone $records)->sum('total_output_kg');
            $waste  = (float) (clone $records)->sum('total_waste_kg');

            return [
                'stage'     => $stage,
                'sesi'      => (clone $records)->count(),
                'input_kg'  => round($input, 2),
                'output_kg' => round($output, 2),
                'waste_kg'  => round($waste, 2),
                'yield_pct' => $input > 0 ? round(($output / $input) * 100, 2) : 0.0,
            ];
        });
    }

    /** Recovery per PO. */
    public function perPurchaseOrder(?string $from = null, ?string $to = null): Collection
    {
        return PurchaseOrder::query()
            ->when($from, fn ($q) => $q->whereDate('order_date', '>=', $from))
            ->when($to,   fn ($q) => $q->whereDate('order_date', '<=', $to))
            ->withSum('receivings as bahan_kg', 'weight_kg')
            ->withSum(['packingBoxes as packing_kg' => fn ($q) => $q->where('status', '<>', 'voided')], 'net_weight_kg')
            ->orderByDesc('order_date')
            ->get()
            ->map(function (PurchaseOrder $po) {
                $bahan   = round((float) ($po->bahan_kg ?? 0), 2);
                $packing = round((float) ($po->packing_kg ?? 0), 2);

                return [
                    'po'           => $po,
                    'bahan_kg'     => $bahan,
                    'packing_kg'   => $packing,
                    'susut_kg'     => round($bahan - $packing, 2),
                    'recovery_pct' => $bahan > 0 ? round(($packing / $bahan) * 100, 2) : 0.0,
                ];
            })
            ->filter(fn ($row) => $row['bahan_kg'] > 0)
            ->values();
    }

    /**
     * Sesi yang yield-nya di bawah standar toleransi.
     *
     * Dinilai memakai standar yang berlaku pada TANGGAL PRODUKSI, bukan hari
     * ini, supaya menaikkan standar tidak membuat produksi lama ikut merah.
     */
    public function alerts(?string $from = null, ?string $to = null, int $limit = 100): Collection
    {
        $records = BatchStageRecord::completed()
            ->with(['stage', 'purchaseOrder', 'inputs.batch.subgroup'])
            ->when($from, fn ($q) => $q->whereDate('process_date', '>=', $from))
            ->when($to,   fn ($q) => $q->whereDate('process_date', '<=', $to))
            ->orderByDesc('process_date')
            ->limit(500)
            ->get();

        return $records->map(function (BatchStageRecord $record) {
            $subgroupId = $record->inputs->first()?->batch?->material_subgroup_id;

            $standard = YieldStandard::resolve($subgroupId, $record->process_stage_id, $record->process_date);
            if (!$standard) {
                return null;
            }

            $hasil = $standard->evaluate((float) $record->yield_pct);
            if ($hasil['level'] === 'ok') {
                return null;
            }

            $selisih = (float) $standard->min_yield_pct - (float) $record->yield_pct;

            return [
                'record'    => $record,
                'standard'  => $standard,
                'level'     => $hasil['level'],
                'message'   => $hasil['message'],
                'subgroup'  => $record->inputs->first()?->batch?->subgroup?->name,
                'selisih_pct' => round($selisih, 2),
                // Berapa kg yang "hilang" dibanding standar minimal.
                'selisih_kg'  => round((float) $record->total_input_kg * max(0, $selisih) / 100, 2),
            ];
        })->filter()->take($limit)->values();
    }

    /** Tren recovery harian untuk grafik. */
    public function dailyTrend(int $days = 30): array
    {
        $mulai = today()->subDays($days - 1);

        $bahan = Receiving::selectRaw('DATE(received_date) as tanggal, SUM(weight_kg) as kg')
            ->whereDate('received_date', '>=', $mulai)
            ->groupBy('tanggal')->pluck('kg', 'tanggal');

        $packing = PackingBox::active()
            ->selectRaw('DATE(packing_date) as tanggal, SUM(net_weight_kg) as kg')
            ->whereDate('packing_date', '>=', $mulai)
            ->groupBy('tanggal')->pluck('kg', 'tanggal');

        $labels = [];
        $nilai  = [];

        for ($i = 0; $i < $days; $i++) {
            $hari  = $mulai->copy()->addDays($i);
            $key   = $hari->toDateString();
            $masuk = (float) ($bahan[$key] ?? 0);
            $keluar = (float) ($packing[$key] ?? 0);

            $labels[] = $hari->format('d/m');
            $nilai[]  = $masuk > 0 ? round(($keluar / $masuk) * 100, 1) : null;
        }

        return ['labels' => $labels, 'values' => $nilai];
    }

    /** Yield per bahan per tahap, untuk tabel alert. */
    public function perSubgroupStage(?string $from = null, ?string $to = null): Collection
    {
        // Tahap ini hanya mencari pasangan (bahan, tahap) yang benar-benar ada.
        // Beratnya TIDAK boleh dijumlah di sini: satu sesi bisa punya beberapa
        // baris input sehingga join akan melipatgandakan totalnya.
        $rows = DB::table('batch_stage_records as r')
            ->join('batch_stage_inputs as i', 'i.batch_stage_record_id', '=', 'r.id')
            ->join('production_batches as b', 'b.id', '=', 'i.batch_id')
            ->join('material_subgroups as s', 's.id', '=', 'b.material_subgroup_id')
            ->join('process_stages as st', 'st.id', '=', 'r.process_stage_id')
            ->where('r.status', 'completed')
            ->when($from, fn ($q) => $q->whereDate('r.process_date', '>=', $from))
            ->when($to,   fn ($q) => $q->whereDate('r.process_date', '<=', $to))
            ->groupBy('s.id', 's.name', 'st.id', 'st.name', 'st.sequence')
            ->orderBy('st.sequence')->orderBy('s.name')
            ->get([
                's.id as subgroup_id', 's.name as subgroup',
                'st.id as stage_id', 'st.name as stage', 'st.sequence',
                DB::raw('COUNT(DISTINCT r.id) as sesi'),
            ]);
        return $rows->map(function ($row) use ($from, $to) {
            $records = BatchStageRecord::completed()
                ->where('process_stage_id', $row->stage_id)
                ->whereHas('inputs.batch', fn ($q) => $q->where('material_subgroup_id', $row->subgroup_id))
                ->when($from, fn ($q) => $q->whereDate('process_date', '>=', $from))
                ->when($to,   fn ($q) => $q->whereDate('process_date', '<=', $to));

            $input  = (float) (clone $records)->sum('total_input_kg');
            $output = (float) (clone $records)->sum('total_output_kg');
            $yield  = $input > 0 ? round(($output / $input) * 100, 2) : 0.0;

            $standard = YieldStandard::resolve((int) $row->subgroup_id, (int) $row->stage_id, today());

            return [
                'subgroup'  => $row->subgroup,
                'stage'     => $row->stage,
                'sesi'      => (int) $row->sesi,
                'input_kg'  => round($input, 2),
                'output_kg' => round($output, 2),
                'yield_pct' => $yield,
                'standard'  => $standard,
                'level'     => $standard ? $standard->evaluate($yield)['level'] : 'none',
            ];
        });
    }
}
