<?php

namespace App\Http\Controllers;

use App\Models\BatchStageOutput;
use App\Models\BatchStageRecord;
use App\Models\ProcessStage;
use App\Models\ProductGroup;
use App\Models\ProductionBatch;
use App\Models\PurchaseOrder;
use App\Models\YieldStandard;
use App\Services\StageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Satu controller untuk semua tahap (Potong / Trimming / Potong Steak).
 * Perbedaan antar tahap ada di data `process_stages`, bukan di tiga controller
 * kembar yang harus diubah bersamaan setiap kali aturannya berubah.
 */
class StageController extends Controller
{
    public function __construct(private readonly StageService $stages)
    {
    }

    public function index(ProcessStage $stage, Request $request): View
    {
        $records = BatchStageRecord::with(['purchaseOrder', 'stage', 'creator'])
            ->where('process_stage_id', $stage->id)
            ->when($request->filled('po_id'), fn ($q) => $q->where('purchase_order_id', $request->po_id))
            ->orderByDesc('process_date')->orderByDesc('id')
            ->paginate(25)->withQueryString();

        return view('stage.index', [
            'stage'   => $stage,
            'records' => $records,
            'pos'     => PurchaseOrder::orderByDesc('order_date')->get(['id', 'po_number']),
            'stats'   => [
                'sesi'       => BatchStageRecord::where('process_stage_id', $stage->id)->completed()->count(),
                'input_kg'   => (float) BatchStageRecord::where('process_stage_id', $stage->id)->completed()->sum('total_input_kg'),
                'output_kg'  => (float) BatchStageRecord::where('process_stage_id', $stage->id)->completed()->sum('total_output_kg'),
                'waste_kg'   => (float) BatchStageRecord::where('process_stage_id', $stage->id)->completed()->sum('total_waste_kg'),
            ],
        ]);
    }

    public function create(ProcessStage $stage, Request $request): View
    {
        $selectedPO = $request->filled('po_id')
            ? PurchaseOrder::with('items.productType')->find($request->po_id)
            : null;

        return view('stage.create', [
            'stage'         => $stage,
            'previousStage' => $stage->previous(),
            'activePOs'     => PurchaseOrder::active()->orderBy('po_number')->get(),
            'selectedPO'    => $selectedPO,
            'productGroups' => ProductGroup::active()
                ->with(['productTypes' => fn ($q) => $q->active()->orderBy('name')])
                ->orderBy('name')->get(),
            'wasteCategories' => BatchStageOutput::WASTE_CATEGORIES,
            // Dikirim ke browser supaya panel yield bisa memberi peringatan
            // sebelum operator menekan simpan, bukan setelahnya.
            'standards'     => $this->standardsForStage($stage),
        ]);
    }

    public function store(ProcessStage $stage, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'purchase_order_id'          => 'required|exists:purchase_orders,id',
            'process_date'               => 'required|date',
            'start_time'                 => 'nullable|date_format:H:i',
            'end_time'                   => 'nullable|date_format:H:i|after_or_equal:start_time',
            'operator_name'              => 'nullable|string|max:200',
            'shift'                      => 'nullable|string|max:20',
            'line_name'                  => 'nullable|string|max:50',
            'notes'                      => 'nullable|string|max:1000',

            'inputs'                     => 'required|array|min:1',
            'inputs.*.batch_id'          => 'required|exists:production_batches,id',
            'inputs.*.input_weight_kg'   => 'required|numeric|min:0.001',

            'outputs'                    => 'required|array|min:1',
            'outputs.*.output_type'      => 'required|in:utama,sampingan',
            'outputs.*.product_type_id'  => ($stage->requires_product_type ? 'required' : 'nullable') . '|exists:product_types,id',
            'outputs.*.output_weight_kg' => 'required|numeric|min:0.001',
            'outputs.*.container_type'   => 'nullable|string|max:100',
            'outputs.*.container_count'  => 'nullable|integer|min:1',
            'outputs.*.notes'            => 'nullable|string|max:255',

            'wastes'                     => ($stage->code === ProcessStage::TRIMMING ? 'required' : 'nullable') . '|array',
            'wastes.*.waste_category'    => 'required|string|max:60',
            'wastes.*.output_weight_kg'  => 'required|numeric|min:0.001',
            'wastes.*.notes'             => 'nullable|string|max:255',
        ], [
            // Permintaan pabrik: trimming wajib mencatat berat daging bersih
            // DAN berat limbahnya untuk evaluasi efisiensi.
            'wastes.required' => 'Tahap Trimming wajib mencatat limbah (darah, duri, kulit) minimal satu baris.',
            'outputs.*.product_type_id.required' => 'Tahap ini wajib memilih tipe produk untuk tiap hasil.',
        ]);

        try {
            $hasil = $this->stages->record($stage, $validated + ['allocations' => $request->input('allocations')]);
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['inputs' => $e->getMessage()]);
        }

        $record = $hasil['record'];
        $alerts = $hasil['alerts'];

        $pesan = "{$stage->name} {$record->record_code} tercatat. "
               . "Input {$record->total_input_kg} kg, hasil {$record->total_output_kg} kg, yield {$record->yield_pct}%.";

        $redirect = redirect()->route('stage.show', $record)->with('success', $pesan);

        if ($alerts) {
            $redirect->with('yield_alerts', array_map(fn ($a) => [
                'level'   => $a['level'],
                'message' => $a['message'],
            ], $alerts));
        }

        return $redirect;
    }

    public function show(BatchStageRecord $record): View
    {
        $record->load([
            'stage', 'purchaseOrder', 'creator', 'voider',
            'inputs.batch.supplier', 'inputs.batch.subgroup',
            'outputs.productType', 'outputs.outputBatch',
            'links.sourceBatch', 'links.outputBatch',
        ]);

        $standard = YieldStandard::resolve(
            $record->inputs->first()?->batch?->material_subgroup_id,
            $record->process_stage_id,
            $record->process_date
        );

        return view('stage.show', [
            'record'     => $record,
            'standard'   => $standard,
            'evaluation' => $standard?->evaluate((float) $record->yield_pct),
        ]);
    }

    public function void(BatchStageRecord $record, Request $request): RedirectResponse
    {
        $request->validate(['void_reason' => 'required|string|max:500']);

        try {
            $this->stages->void($record, $request->void_reason);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['void_reason' => $e->getMessage()]);
        }

        return redirect()->route('stage.show', $record)
            ->with('success', 'Sesi dibatalkan. Bahan dikembalikan ke batch asal dan seluruh langkahnya tercatat di ledger.');
    }

    /**
     * Batch yang boleh menjadi input tahap ini, yaitu hasil tahap sebelumnya
     * (atau bahan baku dari penerimaan bila ini tahap pertama).
     */
    public function apiAvailableInputs(ProcessStage $stage, Request $request): JsonResponse
    {
        $previous = $stage->previous();

        $batches = ProductionBatch::with(['supplier', 'subgroup', 'productType', 'stage'])
            ->available()
            ->when($request->filled('po_id'), fn ($q) => $q->where('purchase_order_id', $request->po_id))
            ->when($previous,
                fn ($q) => $q->where('process_stage_id', $previous->id),
                fn ($q) => $q->whereNull('process_stage_id')
            )
            ->orderBy('effective_date')->orderBy('batch_number')
            ->limit(300)->get();

        return response()->json($batches->map(fn ($b) => [
            'id'            => $b->id,
            'batch_number'  => $b->batch_number,
            'qr_code'       => $b->qr_code,
            'available_kg'  => round((float) $b->current_weight_kg, 3),
            'supplier_code' => $b->supplier?->code,
            // subgroup_id dipakai form untuk memilih standar yield yang tepat.
            'subgroup_id'   => $b->material_subgroup_id,
            'subgroup'      => $b->subgroup?->name,
            'product'       => $b->productType?->name,
            'stage_label'   => $b->stage_label,
            'po_id'         => $b->purchase_order_id,
        ])->values());
    }

    /** Standar yield yang berlaku hari ini, dikelompokkan per subgroup. */
    private function standardsForStage(ProcessStage $stage): array
    {
        return YieldStandard::effectiveOn(today())
            ->where('scope', 'stage')
            ->where(fn ($q) => $q->where('process_stage_id', $stage->id)->orWhereNull('process_stage_id'))
            ->get()
            ->mapWithKeys(fn ($s) => [
                (string) ($s->material_subgroup_id ?? 'default') => [
                    'min'    => (float) $s->min_yield_pct,
                    'warn'   => $s->warn_yield_pct !== null ? (float) $s->warn_yield_pct : null,
                    'target' => $s->target_yield_pct !== null ? (float) $s->target_yield_pct : null,
                ],
            ])->all();
    }
}
