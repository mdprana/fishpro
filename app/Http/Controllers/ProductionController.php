<?php

namespace App\Http\Controllers;

use App\Models\BatchStageOutput;
use App\Models\BatchStageRecord;
use App\Models\PackingBox;
use App\Models\ProcessStage;
use App\Models\ProductionBatch;
use App\Models\PurchaseOrder;
use App\Models\YieldEstimate;
use App\Models\YieldStandard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ringkasan lintas tahap dan analisa per PO.
 *
 * Pencatatan produksi sendiri dilakukan per tahap lewat StageController;
 * controller ini hanya membaca.
 */
class ProductionController extends Controller
{
    /** Ringkasan seluruh sesi dari semua tahap. */
    public function index(Request $request): View
    {
        $records = BatchStageRecord::with(['stage', 'purchaseOrder', 'creator'])
            ->when($request->filled('stage'), fn ($q) => $q->whereHas('stage', fn ($s) => $s->where('code', $request->stage)))
            ->when($request->filled('po_id'), fn ($q) => $q->where('purchase_order_id', $request->po_id))
            ->orderByDesc('process_date')->orderByDesc('id')
            ->paginate(25)->withQueryString();

        $hariIni = BatchStageRecord::completed()->whereDate('process_date', today());

        return view('production.index', [
            'records' => $records,
            'stages'  => ProcessStage::active()->ordered()->get(),
            'pos'     => PurchaseOrder::orderByDesc('order_date')->get(['id', 'po_number']),
            'today'   => [
                'sesi'      => (clone $hariIni)->count(),
                'input_kg'  => round((float) (clone $hariIni)->sum('total_input_kg'), 2),
                'output_kg' => round((float) (clone $hariIni)->sum('total_output_kg'), 2),
                'waste_kg'  => round((float) (clone $hariIni)->sum('total_waste_kg'), 2),
            ],
        ]);
    }

    public function analisa(Request $request): View
    {
        $pos      = PurchaseOrder::orderByDesc('order_date')->get();
        $selected = $request->filled('po_id') ? PurchaseOrder::find($request->po_id) : null;

        return view('production.analisa', [
            'pos'      => $pos,
            'po'       => $selected,
            'analisa'  => $selected ? $this->buildAnalisa($selected) : null,
        ]);
    }

    public function exportPdf(PurchaseOrder $po): View
    {
        return view('production.analisa_pdf', [
            'po'      => $po,
            'analisa' => $this->buildAnalisa($po),
        ]);
    }

    public function exportCsv(PurchaseOrder $po): StreamedResponse
    {
        $a    = $this->buildAnalisa($po);
        $nama = 'analisa-' . str_replace(['/', ' '], '-', $po->po_number) . '-' . now()->format('Ymd') . '.csv';

        return response()->stream(function () use ($po, $a) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['ANALISA PRODUKSI']);
            fputcsv($out, ['Nomor PO', $po->po_number]);
            fputcsv($out, ['Buyer', $po->buyer_name]);
            fputcsv($out, ['Recovery (%)', $a['recovery']['recovery_pct']]);
            fputcsv($out, []);

            // Bagian ini adalah tambahan penting: rekap dari penerimaannya,
            // bukan hanya hasil olahan akhir.
            fputcsv($out, ['REKAP BAHAN BAKU PER SUPPLIER']);
            fputcsv($out, ['Kode', 'Supplier', 'Batch', 'Masuk (kg)', 'Terpakai (kg)', 'Sisa (kg)']);
            foreach ($a['bahanPerSupplier'] as $r) {
                fputcsv($out, [$r['code'], $r['name'], $r['batch'], $r['masuk_kg'], $r['terpakai_kg'], $r['sisa_kg']]);
            }

            fputcsv($out, []);
            fputcsv($out, ['REKAP BAHAN BAKU PER BATCH']);
            fputcsv($out, ['Batch', 'Supplier', 'Bahan', 'Tgl Bongkar', 'Masuk (kg)', 'Terpakai (kg)', 'Sisa (kg)', 'Status']);
            foreach ($a['bahanPerBatch'] as $r) {
                fputcsv($out, [
                    $r['batch_number'], $r['supplier'], $r['subgroup'], $r['tgl_bongkar'],
                    $r['masuk_kg'], $r['terpakai_kg'], $r['sisa_kg'], $r['status'],
                ]);
            }

            fputcsv($out, []);
            fputcsv($out, ['RINGKASAN PER TAHAP']);
            fputcsv($out, ['Tahap', 'Sesi', 'Masuk (kg)', 'Hasil (kg)', 'Limbah (kg)', 'Yield (%)', 'Standar Min (%)']);
            foreach ($a['perTahap'] as $r) {
                fputcsv($out, [$r['stage'], $r['sesi'], $r['input_kg'], $r['output_kg'], $r['waste_kg'], $r['yield_pct'], $r['standard_min']]);
            }

            fputcsv($out, []);
            fputcsv($out, ['SESI PRODUKSI']);
            fputcsv($out, ['Kode', 'Tahap', 'Tanggal', 'Operator', 'Masuk (kg)', 'Hasil (kg)', 'Limbah (kg)', 'Yield (%)']);
            foreach ($a['sesi'] as $r) {
                fputcsv($out, [$r['code'], $r['stage'], $r['date'], $r['operator'], $r['input_kg'], $r['output_kg'], $r['waste_kg'], $r['yield_pct']]);
            }

            fputcsv($out, []);
            fputcsv($out, ['LIMBAH PER KATEGORI']);
            fputcsv($out, ['Tahap', 'Kategori', 'Berat (kg)']);
            foreach ($a['limbah'] as $r) {
                fputcsv($out, [$r['stage'], $r['kategori'], $r['kg']]);
            }

            fputcsv($out, []);
            fputcsv($out, ['ESTIMASI VS AKTUAL']);
            fputcsv($out, ['Bahan', 'Produk', 'Input Bahan (kg)', 'Est. Yield (%)', 'Est. (kg)', 'Aktual (kg)', 'Yield Aktual (%)', 'Selisih (kg)']);
            foreach ($a['estimasiVsAktual'] as $r) {
                fputcsv($out, [
                    $r['bahan'], $r['produk'], $r['input_kg'], $r['est_pct'],
                    $r['est_kg'], $r['aktual_kg'], $r['yield_aktual'], $r['selisih_kg'],
                ]);
            }

            fputcsv($out, []);
            fputcsv($out, ['TARGET PO VS PACKING']);
            fputcsv($out, ['Produk', 'Target (kg)', 'Target Box', 'Packing (kg)', 'Packing Box', 'Selisih (kg)', 'Status']);
            foreach ($a['targetVsPacking'] as $r) {
                fputcsv($out, [$r['produk'], $r['target_kg'], $r['target_box'], $r['packing_kg'], $r['packing_box'], $r['selisih_kg'], $r['status']]);
            }

            fclose($out);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$nama}\"",
        ]);
    }

    // ── Perhitungan analisa ───────────────────────────────────────

    private function buildAnalisa(PurchaseOrder $po): array
    {
        $records = BatchStageRecord::completed()
            ->with('stage')->where('purchase_order_id', $po->id)
            ->orderBy('process_date')->get();

        $outputs = BatchStageOutput::with(['productType', 'record.stage'])
            ->whereIn('batch_stage_record_id', $records->pluck('id'))->get();

        $batches = ProductionBatch::with(['supplier', 'subgroup'])
            ->where('purchase_order_id', $po->id)
            ->where('content_type', 'raw')
            ->whereNotNull('receiving_id')
            ->get()
            // Induk yang sudah dipecah hanya simpul silsilah, bukan stok fisik.
            ->filter(fn ($b) => !$b->children()->where('origin', 'receiving')->exists());

        return [
            'recovery'         => [
                'bahan_kg'     => round((float) $po->total_received_kg, 2),
                'packing_kg'   => round((float) $po->total_packed_kg, 2),
                'recovery_pct' => $po->recovery_pct,
                'susut_kg'     => round((float) $po->total_received_kg - (float) $po->total_packed_kg, 2),
            ],
            'bahanPerSupplier' => $this->bahanPerSupplier($batches),
            'bahanPerBatch'    => $this->bahanPerBatch($batches),
            'perTahap'         => $this->perTahap($records),
            'sesi'             => $this->daftarSesi($records),
            'limbah'           => $this->limbahPerKategori($outputs),
            'outputProduk'     => $this->outputPerProduk($outputs),
            'estimasiVsAktual' => $this->estimasiVsAktual($po, $records, $outputs),
            'targetVsPacking'  => $this->targetVsPacking($po),
            'sisaLot'          => $this->sisaLot($po),
        ];
    }

    private function bahanPerBatch($batches): array
    {
        return $batches->map(fn (ProductionBatch $b) => [
            'batch_number' => $b->batch_number,
            'qr_code'      => $b->qr_code,
            'supplier'     => $b->supplier?->display_name ?? '-',
            'supplier_code' => $b->supplier?->code ?? '-',
            'subgroup'     => $b->subgroup?->name ?? '-',
            'tgl_bongkar'  => $b->effective_date?->format('Y-m-d'),
            'masuk_kg'     => round((float) $b->initial_weight_kg, 3),
            'terpakai_kg'  => $b->consumed_kg,
            'sisa_kg'      => round((float) $b->current_weight_kg, 3),
            'status'       => $b->status_label,
            'status_color' => $b->status_color,
        ])->sortBy('tgl_bongkar')->values()->all();
    }

    private function bahanPerSupplier($batches): array
    {
        return $batches->groupBy(fn ($b) => $b->supplier?->code ?? '-')
            ->map(fn ($rows, $code) => [
                'code'        => $code,
                'name'        => $rows->first()->supplier?->name ?? '-',
                'batch'       => $rows->count(),
                'masuk_kg'    => round($rows->sum(fn ($b) => (float) $b->initial_weight_kg), 3),
                'terpakai_kg' => round($rows->sum(fn ($b) => $b->consumed_kg), 3),
                'sisa_kg'     => round($rows->sum(fn ($b) => (float) $b->current_weight_kg), 3),
            ])
            ->sortByDesc('masuk_kg')->values()->all();
    }

    private function perTahap($records): array
    {
        return $records->groupBy('process_stage_id')->map(function ($rows) {
            $stage  = $rows->first()->stage;
            $input  = round($rows->sum(fn ($r) => (float) $r->total_input_kg), 3);
            $output = round($rows->sum(fn ($r) => (float) $r->total_output_kg), 3);
            $waste  = round($rows->sum(fn ($r) => (float) $r->total_waste_kg), 3);

            $standard = YieldStandard::resolve(null, $stage->id, $rows->first()->process_date);

            return [
                'stage'        => $stage->name,
                'sequence'     => $stage->sequence,
                'color'        => $stage->color,
                'icon'         => $stage->icon,
                'sesi'         => $rows->count(),
                'input_kg'     => $input,
                'output_kg'    => $output,
                'waste_kg'     => $waste,
                'yield_pct'    => $input > 0 ? round(($output / $input) * 100, 2) : 0.0,
                'standard_min' => $standard ? (float) $standard->min_yield_pct : null,
            ];
        })->sortBy('sequence')->values()->all();
    }

    private function daftarSesi($records): array
    {
        return $records->map(fn (BatchStageRecord $r) => [
            'id'        => $r->id,
            'code'      => $r->record_code,
            'stage'     => $r->stage->name,
            'color'     => $r->stage->color,
            'date'      => $r->process_date->format('Y-m-d'),
            'operator'  => $r->operator_name ?: '-',
            'input_kg'  => round((float) $r->total_input_kg, 3),
            'output_kg' => round((float) $r->total_output_kg, 3),
            'waste_kg'  => round((float) $r->total_waste_kg, 3),
            'yield_pct' => (float) $r->yield_pct,
            'notes'     => $r->notes,
        ])->all();
    }

    private function limbahPerKategori($outputs): array
    {
        return $outputs->where('output_type', 'waste')
            ->groupBy(fn ($o) => $o->record->stage->name . '|' . $o->waste_category)
            ->map(function ($rows) {
                $first = $rows->first();
                return [
                    'stage'    => $first->record->stage->name,
                    'kategori' => $first->waste_category_label ?? '-',
                    'kg'       => round($rows->sum(fn ($o) => (float) $o->output_weight_kg), 3),
                ];
            })->sortByDesc('kg')->values()->all();
    }

    private function outputPerProduk($outputs): array
    {
        return $outputs->whereIn('output_type', ['utama', 'sampingan'])
            ->groupBy(fn ($o) => ($o->product_type_id ?? 0) . '|' . $o->output_type)
            ->map(function ($rows) {
                $first = $rows->first();
                return [
                    'produk' => $first->productType?->name ?? 'Hasil tanpa tipe produk',
                    'color'  => $first->productType?->effective_color ?? '#64748b',
                    'jenis'  => $first->output_type,
                    'stage'  => $first->record->stage->name,
                    'kg'     => round($rows->sum(fn ($o) => (float) $o->output_weight_kg), 3),
                ];
            })->sortByDesc('kg')->values()->all();
    }

    /**
     * Estimasi vs aktual, dihitung per pasangan (bahan, produk).
     *
     * Versi lama membagi dengan total input SELURUH bahan dan mengambil satu
     * baris estimasi sembarang, sehingga angkanya tidak bermakna begitu satu
     * produk bisa berasal dari lebih dari satu jenis bahan.
     */
    private function estimasiVsAktual(PurchaseOrder $po, $records, $outputs): array
    {
        // Berapa kg tiap jenis bahan yang benar-benar masuk ke tiap produk,
        // diambil dari jejak kontribusi batch_stage_links.
        $kontribusi = DB::table('batch_stage_links as l')
            ->join('production_batches as ob', 'ob.id', '=', 'l.output_batch_id')
            ->join('production_batches as sb', 'sb.id', '=', 'l.source_batch_id')
            ->join('batch_stage_records as r', 'r.id', '=', 'l.batch_stage_record_id')
            ->where('r.purchase_order_id', $po->id)
            ->where('r.status', 'completed')
            ->whereNotNull('ob.product_type_id')
            ->groupBy('sb.material_subgroup_id', 'ob.product_type_id')
            ->get([
                'sb.material_subgroup_id',
                'ob.product_type_id',
                DB::raw('SUM(l.contributed_kg) as kg'),
            ]);

        // Total bahan yang dipakai per jenis, sebagai pembagi yang benar.
        $inputPerBahan = DB::table('batch_stage_inputs as i')
            ->join('production_batches as b', 'b.id', '=', 'i.batch_id')
            ->join('batch_stage_records as r', 'r.id', '=', 'i.batch_stage_record_id')
            ->join('process_stages as st', 'st.id', '=', 'r.process_stage_id')
            ->where('r.purchase_order_id', $po->id)
            ->where('r.status', 'completed')
            // Hanya tahap pertama; tahap berikutnya memakai ulang bahan yang sama.
            ->where('st.sequence', ProcessStage::active()->min('sequence'))
            ->groupBy('b.material_subgroup_id')
            ->pluck(DB::raw('SUM(i.input_weight_kg)'), 'b.material_subgroup_id');

        return $kontribusi->map(function ($row) use ($inputPerBahan) {
            $subgroup = \App\Models\MaterialSubgroup::find($row->material_subgroup_id);
            $produk   = \App\Models\ProductType::find($row->product_type_id);

            $inputKg = (float) ($inputPerBahan[$row->material_subgroup_id] ?? 0);
            $aktual  = round((float) $row->kg, 3);

            // Estimasi diambil untuk pasangan bahan-produk yang tepat.
            $estimate = YieldEstimate::where('material_subgroup_id', $row->material_subgroup_id)
                ->where('product_type_id', $row->product_type_id)->first();

            $estPct = $estimate ? (float) $estimate->percentage : null;
            $estKg  = $estPct !== null ? round($inputKg * $estPct / 100, 3) : null;

            return [
                'bahan'        => $subgroup?->name ?? '-',
                'produk'       => $produk?->name ?? '-',
                'color'        => $produk?->effective_color ?? '#64748b',
                'input_kg'     => round($inputKg, 3),
                'est_pct'      => $estPct,
                'est_kg'       => $estKg,
                'aktual_kg'    => $aktual,
                'yield_aktual' => $inputKg > 0 ? round(($aktual / $inputKg) * 100, 2) : 0.0,
                'selisih_kg'   => $estKg !== null ? round($aktual - $estKg, 3) : null,
            ];
        })->sortByDesc('aktual_kg')->values()->all();
    }

    private function targetVsPacking(PurchaseOrder $po): array
    {
        return $po->items->map(function ($item) use ($po) {
            $boxes = PackingBox::active()
                ->where('purchase_order_id', $po->id)
                ->where('product_type_id', $item->product_type_id);

            $kg  = round((float) (clone $boxes)->sum('net_weight_kg'), 3);
            $box = (clone $boxes)->count();

            return [
                'produk'      => $item->productType->name,
                'color'       => $item->productType->effective_color,
                'target_kg'   => (float) $item->target_weight_kg,
                'target_box'  => (int) $item->target_box,
                'packing_kg'  => $kg,
                'packing_box' => $box,
                'selisih_kg'  => round($kg - (float) $item->target_weight_kg, 3),
                'selisih_box' => $box - (int) $item->target_box,
                'status'      => $kg >= (float) $item->target_weight_kg ? 'Terpenuhi' : 'Belum terpenuhi',
            ];
        })->values()->all();
    }

    /** Lot yang sudah jadi tapi belum masuk box. */
    private function sisaLot(PurchaseOrder $po): array
    {
        $final = ProcessStage::where('is_final_stage', true)->pluck('id');

        return ProductionBatch::with('productType')
            ->available()
            ->where('purchase_order_id', $po->id)
            ->whereIn('process_stage_id', $final)
            ->get()
            ->groupBy('product_type_id')
            ->map(fn ($rows) => [
                'produk' => $rows->first()->productType?->name ?? '-',
                'color'  => $rows->first()->productType?->effective_color ?? '#64748b',
                'lot'    => $rows->count(),
                'sisa_kg' => round($rows->sum(fn ($b) => (float) $b->current_weight_kg), 3),
            ])->values()->all();
    }
}
