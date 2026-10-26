<?php

namespace App\Http\Controllers;

use App\Models\MaterialGroup;
use App\Models\ProcessStage;
use App\Models\ProductionBatch;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BatchController extends Controller
{
    public function index(Request $request): View
    {
        $batches = ProductionBatch::with(['supplier', 'subgroup.group', 'productType', 'stage', 'purchaseOrder'])
            ->when($request->filled('po_id'),       fn ($q) => $q->where('purchase_order_id', $request->po_id))
            ->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_id', $request->supplier_id))
            ->when($request->filled('subgroup_id'), fn ($q) => $q->where('material_subgroup_id', $request->subgroup_id))
            ->when($request->filled('stage'), function ($q) use ($request) {
                // "receiving" berarti batch bahan baku yang belum masuk tahap mana pun.
                $request->stage === 'receiving'
                    ? $q->whereNull('process_stage_id')
                    : $q->whereHas('stage', fn ($s) => $s->where('code', $request->stage));
            })
            ->when($request->filled('status'),   fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('effective_date', '>=', $request->date_from))
            ->when($request->filled('date_to'),   fn ($q) => $q->whereDate('effective_date', '<=', $request->date_to))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . $request->q . '%';
                $q->where(fn ($w) => $w->where('batch_number', 'like', $term)->orWhere('qr_code', 'like', $term));
            })
            ->orderByDesc('effective_date')->orderByDesc('id')
            ->paginate(40)->withQueryString();

        return view('batch.index', [
            'batches'   => $batches,
            'pos'       => PurchaseOrder::orderByDesc('order_date')->get(['id', 'po_number']),
            'suppliers' => Supplier::orderBy('code')->get(['id', 'code', 'name']),
            'groups'    => MaterialGroup::with('subgroups')->orderBy('name')->get(),
            'stages'    => ProcessStage::active()->ordered()->get(),
        ]);
    }

    public function show(ProductionBatch $batch): View
    {
        $batch->load([
            'supplier', 'subgroup.group', 'productType', 'stage', 'purchaseOrder',
            'receiving', 'parent', 'rootBatch', 'creator',
            'children.stage', 'children.productType',
            'movements.creator',
        ]);

        return view('batch.show', compact('batch'));
    }

    /**
     * Lembar cetak label QR untuk beberapa batch sekaligus (palet/keranjang).
     */
    public function labels(Request $request): View
    {
        $ids = collect(explode(',', (string) $request->query('ids')))
            ->map(fn ($id) => (int) trim($id))->filter()->all();

        abort_if(empty($ids), 404, 'Tidak ada batch yang dipilih untuk dicetak.');

        $batches = ProductionBatch::with(['supplier', 'subgroup.group', 'purchaseOrder', 'stage', 'productType'])
            ->whereIn('id', $ids)->orderBy('batch_number')->get();

        abort_if($batches->isEmpty(), 404, 'Batch tidak ditemukan.');

        // Dicatat sebagai jejak "label sudah pernah dicetak", bukan pengunci.
        // Label yang rusak di lantai produksi wajar dicetak ulang.
        ProductionBatch::whereIn('id', $batches->pluck('id'))->update(['label_printed_at' => now()]);

        return view('batch.labels', compact('batches'));
    }

    public function printLabel(ProductionBatch $batch): View
    {
        return $this->labels(new Request(['ids' => (string) $batch->id]));
    }

    /**
     * Dipanggil form tahap produksi setelah QR di-scan.
     */
    public function apiLookup(string $qr): JsonResponse
    {
        $batch = ProductionBatch::with(['supplier', 'subgroup', 'stage', 'purchaseOrder', 'productType'])
            ->where('qr_code', $qr)->orWhere('batch_number', $qr)->first();

        if (!$batch) {
            return response()->json(['message' => "Kode {$qr} tidak dikenali."], 404);
        }

        return response()->json($this->toLookupArray($batch));
    }

    /**
     * Daftar batch yang siap dipakai, untuk dropdown alternatif bila kamera
     * tidak tersedia. `stage` adalah tahap YANG AKAN dikerjakan, dan yang
     * dikembalikan adalah calon inputnya, yaitu output tahap sebelumnya.
     */
    public function apiAvailable(Request $request): JsonResponse
    {
        $query = ProductionBatch::with(['supplier', 'subgroup', 'stage', 'productType'])
            ->available()
            ->when($request->filled('po_id'), fn ($q) => $q->where('purchase_order_id', $request->po_id));

        if ($request->filled('stage')) {
            $stage    = ProcessStage::where('code', $request->stage)->firstOrFail();
            $previous = $stage->previous();

            $previous
                ? $query->where('process_stage_id', $previous->id)
                : $query->whereNull('process_stage_id');
        }

        $batches = $query->orderBy('effective_date')->orderBy('batch_number')->limit(300)->get();

        return response()->json($batches->map(fn ($b) => $this->toLookupArray($b))->values());
    }

    private function toLookupArray(ProductionBatch $batch): array
    {
        return [
            'id'            => $batch->id,
            'batch_number'  => $batch->batch_number,
            'qr_code'       => $batch->qr_code,
            'available_kg'  => round((float) $batch->current_weight_kg, 3),
            'initial_kg'    => round((float) $batch->initial_weight_kg, 3),
            'status'        => $batch->status,
            'status_label'  => $batch->status_label,
            'is_available'  => $batch->is_available,
            'stage'         => $batch->stage?->code,
            'stage_label'   => $batch->stage_label,
            // subgroup_id dipakai form tahap untuk memilih standar yield.
            'subgroup_id'   => $batch->material_subgroup_id,
            'subgroup'      => $batch->subgroup?->name,
            'product'       => $batch->productType?->name,
            'supplier_code' => $batch->supplier?->code,
            'supplier'      => $batch->supplier?->name,
            'po_id'         => $batch->purchase_order_id,
            'po_number'     => $batch->purchaseOrder?->po_number,
            'container'     => $batch->container_type,
            'effective_date' => $batch->effective_date?->format('Y-m-d'),
        ];
    }
}
