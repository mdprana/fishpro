<?php

namespace App\Http\Controllers;

use App\Models\MaterialGroup;
use App\Models\PackingBox;
use App\Models\ProductionBatch;
use App\Models\PurchaseOrder;
use App\Models\Receiving;
use App\Models\Supplier;
use App\Services\YieldService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Laporan lintas modul: rekap bahan baku dan dashboard yield/recovery.
 */
class ReportController extends Controller
{
    public function __construct(private readonly YieldService $yield)
    {
    }

    /**
     * Rekap bahan baku: dari supplier mana saja, batch mana saja, dan
     * berapa yang akhirnya menjadi box packing.
     */
    public function rawMaterial(Request $request): View
    {
        $filter = $this->dateFilter($request);

        return view('report.raw_material', [
            'filter'      => $filter,
            'perSupplier' => $this->recapPerSupplier($filter),
            'perBatch'    => $this->recapPerBatch($filter),
            'perSubgroup' => $this->recapPerSubgroup($filter),
            'pos'         => PurchaseOrder::orderByDesc('order_date')->get(['id', 'po_number']),
            'suppliers'   => Supplier::orderBy('code')->get(['id', 'code', 'name']),
            'groups'      => MaterialGroup::with('subgroups')->orderBy('name')->get(),
        ]);
    }

    public function exportRawMaterialCsv(Request $request): StreamedResponse
    {
        $filter      = $this->dateFilter($request);
        $perSupplier = $this->recapPerSupplier($filter);
        $perBatch    = $this->recapPerBatch($filter);

        $nama = 'rekap-bahan-baku-' . now()->format('Ymd-His') . '.csv';

        return response()->stream(function () use ($perSupplier, $perBatch, $filter) {
            $out = fopen('php://output', 'w');
            // BOM supaya Excel membaca UTF-8 dengan benar.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['REKAP BAHAN BAKU']);
            fputcsv($out, ['Periode', ($filter['from'] ?? 'awal') . ' s/d ' . ($filter['to'] ?? 'sekarang')]);
            fputcsv($out, []);

            fputcsv($out, ['PER SUPPLIER']);
            fputcsv($out, ['Kode', 'Supplier', 'Penerimaan', 'Batch', 'Masuk (kg)', 'Terpakai (kg)', 'Sisa (kg)', 'Terpacking (kg)', 'Box', 'Recovery (%)']);
            foreach ($perSupplier as $r) {
                fputcsv($out, [
                    $r['code'], $r['name'], $r['penerimaan'], $r['batch'],
                    $r['masuk_kg'], $r['terpakai_kg'], $r['sisa_kg'],
                    $r['packing_kg'], $r['box'], $r['recovery_pct'],
                ]);
            }

            fputcsv($out, []);
            fputcsv($out, ['PER BATCH INDUK']);
            fputcsv($out, ['Batch', 'Supplier', 'Bahan', 'Tgl Bongkar', 'Masuk (kg)', 'Terpakai (kg)', 'Sisa (kg)', 'Status', 'Terpacking (kg)', 'Box', 'No. PO']);
            foreach ($perBatch as $r) {
                fputcsv($out, [
                    $r['batch_number'], $r['supplier'], $r['subgroup'], $r['tgl_bongkar'],
                    $r['masuk_kg'], $r['terpakai_kg'], $r['sisa_kg'], $r['status'],
                    $r['packing_kg'], $r['box'], $r['po_number'],
                ]);
            }

            fclose($out);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$nama}\"",
        ]);
    }

    /** Dashboard Yield & Recovery beserta alert toleransi. */
    public function yieldRecovery(Request $request): View
    {
        $filter   = $this->dateFilter($request);
        $perStage = $this->yield->perStage($filter['from'], $filter['to']);

        return view('report.yield', [
            'filter'      => $filter,
            'summary'     => $this->yield->summary($filter['from'], $filter['to']),
            'perStage'    => $perStage,
            'perPo'       => $this->yield->perPurchaseOrder($filter['from'], $filter['to']),
            'perSubgroup' => $this->yield->perSubgroupStage($filter['from'], $filter['to']),
            'alerts'      => $this->yield->alerts($filter['from'], $filter['to']),
            'trend'       => $this->yield->dailyTrend(30),
            // Disiapkan di sini, bukan di view: Blade tidak bisa mem-parse
            // closure multi-baris di dalam @json().
            'stageChart'  => [
                'labels' => $perStage->map(fn ($s) => $s['stage']->name)->values(),
                'output' => $perStage->pluck('output_kg')->values(),
                'waste'  => $perStage->pluck('waste_kg')->values(),
            ],
        ]);
    }

    // ── Perhitungan ───────────────────────────────────────────────

    private function dateFilter(Request $request): array
    {
        return [
            'from'        => $request->input('date_from') ?: null,
            'to'          => $request->input('date_to') ?: null,
            'po_id'       => $request->input('po_id') ?: null,
            'supplier_id' => $request->input('supplier_id') ?: null,
            'subgroup_id' => $request->input('subgroup_id') ?: null,
        ];
    }

    /** Batch induk (level penerimaan) sesuai filter. */
    private function batchQuery(array $filter)
    {
        return ProductionBatch::query()
            ->where('content_type', 'raw')
            ->whereNotNull('receiving_id')
            ->when($filter['from'], fn ($q) => $q->whereDate('effective_date', '>=', $filter['from']))
            ->when($filter['to'],   fn ($q) => $q->whereDate('effective_date', '<=', $filter['to']))
            ->when($filter['po_id'], fn ($q) => $q->where('purchase_order_id', $filter['po_id']))
            ->when($filter['supplier_id'], fn ($q) => $q->where('supplier_id', $filter['supplier_id']))
            ->when($filter['subgroup_id'], fn ($q) => $q->where('material_subgroup_id', $filter['subgroup_id']));
    }

    /**
     * Kg dan jumlah box yang berasal dari batch induk tertentu.
     * Memakai kolom denormalisasi packing_box_sources.root_batch_id supaya
     * tidak perlu menelusuri silsilah tiap box satu per satu.
     */
    private function packingPerRootBatch(array $rootIds): array
    {
        if (empty($rootIds)) {
            return [];
        }

        return DB::table('packing_box_sources as s')
            ->join('packing_boxes as b', 'b.id', '=', 's.packing_box_id')
            ->where('b.status', '<>', 'voided')
            ->whereIn('s.root_batch_id', $rootIds)
            ->groupBy('s.root_batch_id')
            ->get([
                's.root_batch_id',
                DB::raw('SUM(s.contributed_kg) as kg'),
                DB::raw('COUNT(DISTINCT s.packing_box_id) as box'),
            ])
            ->keyBy('root_batch_id')
            ->map(fn ($r) => ['kg' => round((float) $r->kg, 3), 'box' => (int) $r->box])
            ->all();
    }

    private function recapPerBatch(array $filter)
    {
        $batches = $this->batchQuery($filter)
            ->with(['supplier', 'subgroup.group', 'purchaseOrder'])
            // Induk yang sudah dipecah per wadah hanya simpul silsilah; yang
            // dihitung sebagai stok fisik adalah lot wadahnya.
            ->get()
            ->filter(fn ($b) => !$b->children()->where('origin', 'receiving')->exists());

        $packing = $this->packingPerRootBatch(
            $batches->pluck('root_batch_id')->merge($batches->pluck('id'))->filter()->unique()->all()
        );

        return $batches->map(function (ProductionBatch $b) use ($packing) {
            $rootId = $b->root_batch_id ?? $b->id;
            $pack   = $packing[$rootId] ?? ['kg' => 0, 'box' => 0];

            return [
                'id'           => $b->id,
                'batch_number' => $b->batch_number,
                'supplier'     => $b->supplier?->display_name ?? '-',
                'supplier_code' => $b->supplier?->code ?? '-',
                'subgroup'     => $b->subgroup?->name ?? '-',
                'group'        => $b->subgroup?->group?->name ?? '-',
                'tgl_bongkar'  => $b->effective_date?->format('Y-m-d'),
                'masuk_kg'     => round((float) $b->initial_weight_kg, 3),
                'terpakai_kg'  => $b->consumed_kg,
                'sisa_kg'      => round((float) $b->current_weight_kg, 3),
                'status'       => $b->status_label,
                'status_color' => $b->status_color,
                'packing_kg'   => $pack['kg'],
                'box'          => $pack['box'],
                'po_number'    => $b->purchaseOrder?->po_number ?? '-',
                'qr_code'      => $b->qr_code,
            ];
        })->sortByDesc('tgl_bongkar')->values();
    }

    private function recapPerSupplier(array $filter)
    {
        $perBatch = $this->recapPerBatch($filter);

        $penerimaan = Receiving::query()
            ->when($filter['from'], fn ($q) => $q->whereDate('received_date', '>=', $filter['from']))
            ->when($filter['to'],   fn ($q) => $q->whereDate('received_date', '<=', $filter['to']))
            ->when($filter['po_id'], fn ($q) => $q->where('purchase_order_id', $filter['po_id']))
            ->when($filter['supplier_id'], fn ($q) => $q->where('supplier_id', $filter['supplier_id']))
            ->selectRaw('supplier_id, COUNT(*) as jumlah')
            ->groupBy('supplier_id')->pluck('jumlah', 'supplier_id');

        return $perBatch->groupBy('supplier_code')->map(function ($rows, $code) use ($penerimaan) {
            $masuk   = round($rows->sum('masuk_kg'), 3);
            $packing = round($rows->sum('packing_kg'), 3);
            $supplier = Supplier::where('code', $code)->first();

            return [
                'code'         => $code,
                'name'         => $supplier?->name ?? '-',
                'penerimaan'   => (int) ($penerimaan[$supplier?->id] ?? 0),
                'batch'        => $rows->count(),
                'masuk_kg'     => $masuk,
                'terpakai_kg'  => round($rows->sum('terpakai_kg'), 3),
                'sisa_kg'      => round($rows->sum('sisa_kg'), 3),
                'packing_kg'   => $packing,
                'box'          => $rows->sum('box'),
                'recovery_pct' => $masuk > 0 ? round(($packing / $masuk) * 100, 2) : 0.0,
            ];
        })->sortByDesc('masuk_kg')->values();
    }

    private function recapPerSubgroup(array $filter)
    {
        return $this->recapPerBatch($filter)
            ->groupBy('subgroup')
            ->map(fn ($rows, $nama) => [
                'subgroup'    => $nama,
                'group'       => $rows->first()['group'],
                'batch'       => $rows->count(),
                'masuk_kg'    => round($rows->sum('masuk_kg'), 3),
                'terpakai_kg' => round($rows->sum('terpakai_kg'), 3),
                'sisa_kg'     => round($rows->sum('sisa_kg'), 3),
                'packing_kg'  => round($rows->sum('packing_kg'), 3),
            ])
            ->sortByDesc('masuk_kg')->values();
    }
}
