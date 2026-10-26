<?php

namespace App\Http\Controllers;

use App\Models\PackingBox;
use App\Models\PackingRun;
use App\Models\ProcessStage;
use App\Models\ProductionBatch;
use App\Models\ProductType;
use App\Models\PurchaseOrder;
use App\Services\PackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Packing per box fisik: satu baris = satu box = satu QR.
 */
class PackingController extends Controller
{
    public function __construct(private readonly PackingService $packing)
    {
    }

    public function index(): View
    {
        $runs = PackingRun::with(['purchaseOrder', 'productType.group', 'creator'])
            ->withCount(['boxes as box_count' => fn ($q) => $q->where('status', '<>', 'voided')])
            ->withSum(['boxes as total_kg' => fn ($q) => $q->where('status', '<>', 'voided')], 'net_weight_kg')
            ->orderByDesc('packing_date')->orderByDesc('id')
            ->paginate(20);

        return view('packing.index', compact('runs'));
    }

    public function create(Request $request): View
    {
        $selectedPO = $request->filled('po_id')
            ? PurchaseOrder::with('items.productType.group')->find($request->po_id)
            : null;

        return view('packing.create', [
            'activePOs'  => PurchaseOrder::active()->orderBy('po_number')->get(),
            'selectedPO' => $selectedPO,
            'products'   => $selectedPO
                ? $this->productsWithStock($selectedPO)
                : collect(),
        ]);
    }

    /** Mode bulk: buat N box sekaligus. */
    public function storeRun(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'purchase_order_id'     => 'required|exists:purchase_orders,id',
            'product_type_id'       => 'required|exists:product_types,id',
            'box_count'             => 'required|integer|min:1|max:2000',
            'weight_per_box_kg'     => 'required|numeric|min:0.001',
            'packing_date'          => 'required|date',
            'operator_name'         => 'nullable|string|max:200',
            'notes'                 => 'nullable|string|max:500',
            'allocation'            => 'required|array|min:1',
            'allocation.*.batch_id' => 'required|exists:production_batches,id',
            'allocation.*.kg'       => 'required|numeric|min:0',
        ]);

        try {
            $hasil = $this->packing->bulkGenerate(
                PurchaseOrder::findOrFail($data['purchase_order_id']),
                ProductType::findOrFail($data['product_type_id']),
                $data['allocation'],
                (int) $data['box_count'],
                (float) $data['weight_per_box_kg'],
                [
                    'packing_date'  => $data['packing_date'],
                    'operator_name' => $data['operator_name'] ?? null,
                    'notes'         => $data['notes'] ?? null,
                ]
            );
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['allocation' => $e->getMessage()]);
        }

        $jumlah = $hasil['boxes']->count();

        return redirect()
            ->route('packing.boxes', ['run_id' => $hasil['run']->id])
            ->with('success', "{$jumlah} box dibuat dengan QR masing-masing. Berat tiap box masih bisa dikoreksi.");
    }

    /** Mode timbang-per-box: satu box per permintaan, dipanggil lewat AJAX. */
    public function weighBox(Request $request): JsonResponse
    {
        $data = $request->validate([
            'purchase_order_id'     => 'required|exists:purchase_orders,id',
            'product_type_id'       => 'required|exists:product_types,id',
            'net_weight_kg'         => 'required|numeric|min:0.001',
            'packing_date'          => 'required|date',
            'operator_name'         => 'nullable|string|max:200',
            'allocation'            => 'required|array|min:1',
            'allocation.*.batch_id' => 'required|exists:production_batches,id',
            'allocation.*.kg'       => 'required|numeric|min:0',
        ]);

        try {
            $box = $this->packing->weighSingleBox(
                PurchaseOrder::findOrFail($data['purchase_order_id']),
                ProductType::findOrFail($data['product_type_id']),
                $data['allocation'],
                (float) $data['net_weight_kg'],
                [
                    'packing_date'  => $data['packing_date'],
                    'operator_name' => $data['operator_name'] ?? null,
                ]
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'id'        => $box->id,
            'box_code'  => $box->box_code,
            'qr_code'   => $box->qr_code,
            'qr_url'    => $box->qr_url,
            'net_kg'    => (float) $box->net_weight_kg,
            'is_mixed'  => $box->is_mixed_batch,
            'label_url' => route('packing.labels', ['ids' => $box->id]),
        ]);
    }

    /** Koreksi berat satu box (edit inline pada daftar box). */
    public function adjustWeight(PackingBox $box, Request $request): JsonResponse
    {
        $data = $request->validate(['net_weight_kg' => 'required|numeric|min:0.001']);

        try {
            $box = $this->packing->adjustBoxWeight($box, (float) $data['net_weight_kg']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'id'     => $box->id,
            'net_kg' => (float) $box->net_weight_kg,
        ]);
    }

    public function voidBox(PackingBox $box, Request $request): RedirectResponse
    {
        $request->validate(['reason' => 'required|string|max:500']);

        try {
            $this->packing->voidBox($box, $request->reason);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['reason' => $e->getMessage()]);
        }

        return back()->with('success', "Box {$box->box_code} dibatalkan dan isinya dikembalikan ke lot asal.");
    }

    /** Daftar box: inilah tampilan "500 box dengan batch & kiloan berbeda". */
    public function boxes(Request $request): View
    {
        $boxes = PackingBox::with(['productType', 'purchaseOrder', 'primaryBatch.supplier', 'sources.supplier', 'sources.rootBatch'])
            ->when($request->filled('run_id'),     fn ($q) => $q->where('packing_run_id', $request->run_id))
            ->when($request->filled('po_id'),      fn ($q) => $q->where('purchase_order_id', $request->po_id))
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_type_id', $request->product_id))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . $request->q . '%';
                $q->where(fn ($w) => $w->where('box_code', 'like', $term)->orWhere('qr_code', 'like', $term));
            })
            ->when($request->boolean('mixed_only'), fn ($q) => $q->where('is_mixed_batch', true))
            ->when($request->filled('batch_id'), fn ($q) => $q->whereHas('sources',
                fn ($s) => $s->where('root_batch_id', $request->batch_id)->orWhere('batch_id', $request->batch_id)))
            ->orderBy('sequence_no')->orderBy('id')
            ->paginate(100)->withQueryString();

        return view('packing.boxes', [
            'boxes' => $boxes,
            'runs'  => PackingRun::orderByDesc('packing_date')->limit(50)->get(['id', 'run_code']),
            'pos'   => PurchaseOrder::orderByDesc('order_date')->get(['id', 'po_number']),
        ]);
    }

    public function labels(Request $request): View
    {
        $ids = collect(explode(',', (string) $request->query('ids')))
            ->map(fn ($id) => (int) trim($id))->filter()->all();

        if (empty($ids) && $request->filled('run_id')) {
            $ids = PackingBox::where('packing_run_id', $request->run_id)->pluck('id')->all();
        }

        abort_if(empty($ids), 404, 'Tidak ada box yang dipilih untuk dicetak.');

        $boxes = PackingBox::with(['productType', 'purchaseOrder', 'sources.supplier'])
            ->whereIn('id', $ids)->orderBy('sequence_no')->get();

        abort_if($boxes->isEmpty(), 404, 'Box tidak ditemukan.');

        PackingBox::whereIn('id', $boxes->pluck('id'))->update(['label_printed_at' => now()]);

        return view('packing.labels', compact('boxes'));
    }

    /** Lot tahap akhir yang siap dipacking, untuk panel pilihan sumber. */
    public function apiAvailableLots(Request $request): JsonResponse
    {
        $final = ProcessStage::where('is_final_stage', true)->pluck('id');

        $lots = ProductionBatch::with(['supplier', 'productType', 'rootBatch.supplier'])
            ->available()
            ->whereIn('process_stage_id', $final)
            ->when($request->filled('po_id'),      fn ($q) => $q->where('purchase_order_id', $request->po_id))
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_type_id', $request->product_id))
            ->orderBy('effective_date')->orderBy('batch_number')
            ->get();

        return response()->json($lots->map(fn ($b) => [
            'id'             => $b->id,
            'batch_number'   => $b->batch_number,
            'available_kg'   => round((float) $b->current_weight_kg, 3),
            'product'        => $b->productType?->name,
            'product_id'     => $b->product_type_id,
            'supplier_code'  => $b->rootBatch?->supplier?->code ?? $b->supplier?->code,
            'effective_date' => $b->effective_date?->format('Y-m-d'),
            'is_mixed'       => (bool) $b->is_multi_source,
        ])->values());
    }

    /**
     * Produk PO ini beserta stok lot siap-packing dan progres box.
     */
    private function productsWithStock(PurchaseOrder $po)
    {
        $final = ProcessStage::where('is_final_stage', true)->pluck('id');

        return $po->items->map(function ($item) use ($po, $final) {
            $tersedia = (float) ProductionBatch::available()
                ->whereIn('process_stage_id', $final)
                ->where('purchase_order_id', $po->id)
                ->where('product_type_id', $item->product_type_id)
                ->sum('current_weight_kg');

            $terpacking = PackingBox::active()
                ->where('purchase_order_id', $po->id)
                ->where('product_type_id', $item->product_type_id);

            $boxTerpacking = (clone $terpacking)->count();
            $kgTerpacking  = (float) (clone $terpacking)->sum('net_weight_kg');

            return [
                'product_type_id' => $item->product_type_id,
                'name'            => $item->productType->name,
                'color'           => $item->productType->effective_color,
                'available_kg'    => round($tersedia, 3),
                'target_box'      => (int) $item->target_box,
                'target_kg'       => (float) $item->target_weight_kg,
                'weight_per_box'  => $item->effective_weight_per_box,
                'packed_box'      => $boxTerpacking,
                'packed_kg'       => round($kgTerpacking, 3),
                'remaining_box'   => max(0, (int) $item->target_box - $boxTerpacking),
            ];
        })->values();
    }
}
