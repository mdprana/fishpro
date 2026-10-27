<?php
namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PoItem;
use App\Models\PoSurplus;
use App\Models\ProcessStage;
use App\Models\ProductGroup;
use App\Models\ProductionBatch;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    public function __construct(private readonly AuditService $audit)
    {
    }

    public function index(): View
    {
        $pos = PurchaseOrder::with(['items.productType.group','items.packingBoxes'])
            ->withCount('items')
            ->withStageDates()          // kolom tanggal tiap tahap, tanpa query per baris
            ->orderByDesc('created_at')->paginate(15);

        return view('po.index', [
            'pos'    => $pos,
            'stages' => ProcessStage::active()->ordered()->get(),
        ]);
    }

    public function create(): View
    {
        $productsByGroup = ProductGroup::active()
            ->with(['productTypes' => fn($q) => $q->active()->orderBy('name')])
            ->orderBy('name')->get()
            ->mapWithKeys(fn($g) => [$g->id => [
                'name'     => $g->name,
                'color'    => $g->color,
                'products' => $g->productTypes->map(fn($pt) => [
                    'id'    => $pt->id,
                    'name'  => $pt->name,
                    'color' => $pt->effective_color,
                ])->toArray(),
            ]]);
        return view('po.create', compact('productsByGroup'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'po_number'  => 'required|string|max:100|unique:purchase_orders,po_number',
            'buyer_name' => 'required|string|max:200',
            'order_date' => 'required|date',
            'deadline'   => 'required|date|after_or_equal:order_date',
            'notes'      => 'nullable|string|max:1000',
            'items'                       => 'required|array|min:1',
            'items.*.product_type_id'     => 'required|exists:product_types,id',
            'items.*.target_weight_kg'    => 'required|numeric|min:0.01',
            'items.*.target_box'          => 'required|integer|min:1',
        ]);

        DB::transaction(function () use ($request) {
            $po = PurchaseOrder::create([
                'po_number'  => $request->po_number,
                'buyer_name' => $request->buyer_name,
                'order_date' => $request->order_date,
                'deadline'   => $request->deadline,
                'status'     => 'active',
                'notes'      => $request->notes,
                'created_by' => auth()->id(),
            ]);
            foreach ($request->items as $item) {
                $resolved = PoItem::resolveBoxWeight($item);
                $po->items()->create([
                    'product_type_id'   => $resolved['product_type_id'],
                    'target_weight_kg'  => $resolved['target_weight_kg'],
                    'target_box'        => $resolved['target_box'],
                    'weight_per_box_kg' => $resolved['weight_per_box_kg'] ?? null,
                    'notes'             => $resolved['notes'] ?? null,
                ]);
            }

            $this->audit->record('po.create', $po, null, $this->audit->snapshot($po->fresh()));
        });

        return redirect()->route('po.index')
            ->with('success', "PO {$request->po_number} berhasil dibuat.");
    }

public function show(PurchaseOrder $po): View
{
    $po->load([
        'items.productType.group',
        'items.packingBoxes',

        'receivings.subgroup.group',
        'receivings.supplier',

        'stageRecords.stage',

        'packingBoxes.productType.group',
        'packingBoxes.poItem',
    ]);

        // Stok setengah jadi kini dibaca dari lot yang benar-benar ada,
        // bukan dari selisih angka output dikurangi packing.
        $semiFinished = ProductionBatch::with('productType.group')
            ->available()
            ->where('purchase_order_id', $po->id)
            ->whereNotNull('product_type_id')
            ->get()
            ->groupBy('product_type_id')
            ->map(function ($lots, $productTypeId) use ($po) {
                $packed = (float) $po->packingBoxes->where('status', '!=', 'voided')
                    ->where('product_type_id', $productTypeId)->sum('net_weight_kg');

                return [
                    'product_type_id' => $productTypeId,
                    'name'      => $lots->first()->productType->name,
                    'color'     => $lots->first()->productType->effective_color,
                    'output_kg' => round($lots->sum(fn ($b) => (float) $b->initial_weight_kg), 2),
                    'packed_kg' => round($packed, 2),
                    'available' => round($lots->sum(fn ($b) => (float) $b->current_weight_kg), 2),
                ];
            })->values();

        $surpluses = PoSurplus::where('source_po_id', $po->id)->with('productType.group')->get();

        return view('po.show', [
            'po'           => $po,
            'semiFinished' => $semiFinished,
            'surpluses'    => $surpluses,
            'timeline'     => $po->stageTimeline(),
            // Bahan baku yang masuk untuk PO ini, berikut sisa tiap batchnya.
            'batches'      => $po->batches()->roots()->with(['supplier', 'subgroup.group'])
                                  ->orderBy('effective_date')->get(),
        ]);
    }

    public function edit(PurchaseOrder $po): View
    {
        $po->load('items.productType.group');
        $productsByGroup = ProductGroup::active()
            ->with(['productTypes' => fn($q) => $q->active()->orderBy('name')])
            ->orderBy('name')->get()
            ->mapWithKeys(fn($g) => [$g->id => [
                'name'     => $g->name,
                'color'    => $g->color,
                'products' => $g->productTypes->map(fn($pt) => [
                    'id'    => $pt->id,
                    'name'  => $pt->name,
                    'color' => $pt->effective_color,
                ])->toArray(),
            ]]);
        return view('po.edit', compact('po', 'productsByGroup'));
    }

    public function update(Request $request, PurchaseOrder $po): RedirectResponse
    {
        $request->validate([
            'buyer_name' => 'required|string|max:200',
            'order_date' => 'required|date',
            'deadline'   => 'required|date|after_or_equal:order_date',
            'status'     => 'required|in:draft,active,in_progress,completed,cancelled',
            'notes'      => 'nullable|string|max:1000',
            'items'                    => 'required|array|min:1',
            'items.*.id'               => 'nullable|integer|exists:po_items,id',
            'items.*.product_type_id'  => 'required|exists:product_types,id',
            'items.*.target_weight_kg' => 'required|numeric|min:0.01',
            'items.*.target_box'       => 'required|integer|min:1',
        ]);

        // Item yang hilang dari form tidak boleh dihapus kalau sudah ada packing.
        // Menghapusnya berarti membuang riwayat produksi yang sudah terjadi.
        $keepIds  = collect($request->items)->pluck('id')->filter()->map('intval')->all();
        $removed  = $po->items()->whereNotIn('id', $keepIds)->get();
        $blocking = $removed->filter(fn (PoItem $item) => $item->packingBoxes()->exists());

        if ($blocking->isNotEmpty()) {
            $names = $blocking->load('productType')
                ->map(fn (PoItem $item) => $item->productType?->name ?? "Item #{$item->id}")
                ->implode(', ');

            return back()->withInput()->withErrors([
                'items' => "Produk berikut sudah punya data packing sehingga tidak bisa dihapus dari PO: {$names}. "
                         . "Hapus data packing-nya lebih dulu bila memang ingin mengubah item ini.",
            ]);
        }

        DB::transaction(function () use ($request, $po, $removed) {
            $before = $this->audit->snapshot(PurchaseOrder::whereKey($po->id)->lockForUpdate()->firstOrFail());

            $po->update([
                'buyer_name' => $request->buyer_name,
                'order_date' => $request->order_date,
                'deadline'   => $request->deadline,
                'status'     => $request->status,
                'notes'      => $request->notes,
            ]);

            // Diff-sync, BUKAN delete-all-lalu-create-ulang. Pola lama memicu
            // cascade DB yang menghapus seluruh packing_results milik PO ini.
            foreach ($request->items as $item) {
                $resolved = PoItem::resolveBoxWeight($item);
                $payload  = [
                    'product_type_id'   => $resolved['product_type_id'],
                    'target_weight_kg'  => $resolved['target_weight_kg'],
                    'target_box'        => $resolved['target_box'],
                    'weight_per_box_kg' => $resolved['weight_per_box_kg'] ?? null,
                    'notes'             => $resolved['notes'] ?? null,
                ];

                if (!empty($item['id'])) {
                    $po->items()->whereKey($item['id'])->update($payload);
                } else {
                    $po->items()->create($payload);
                }
            }

            foreach ($removed as $item) {
                $item->delete(); // soft delete, riwayat tetap tersimpan
            }

            $this->audit->record('po.update', $po, $before, $this->audit->snapshot($po->fresh()));
        });

        return redirect()->route('po.show', $po)->with('success', "PO diperbarui.");
    }

    public function destroy(PurchaseOrder $po): RedirectResponse
    {
        DB::transaction(function () use ($po) {
            $locked = PurchaseOrder::whereKey($po->id)->lockForUpdate()->firstOrFail();
            $before = $this->audit->snapshot($locked);
            $locked->delete();
            $this->audit->record('po.delete', $locked, $before, $this->audit->snapshot($locked));
        });

        return redirect()->route('po.index')->with('success', "PO {$po->po_number} dihapus.");
    }

    public function calcItem(Request $request): JsonResponse
    {
        $w = (float)($request->target_weight_kg ?? 0);
        $b = (int)($request->target_box ?? 0);
        $p = (float)($request->weight_per_box_kg ?? 0);
        $result = [];
        if ($w > 0 && $b > 0) $result['weight_per_box_kg'] = round($w / $b, 2);
        if ($w > 0 && $p > 0) $result['target_box'] = (int)ceil($w / $p);
        if ($b > 0 && $p > 0) $result['target_weight_kg'] = round($b * $p, 2);
        return response()->json($result);
    }

    public function activePOs(): JsonResponse
    {
        $pos = PurchaseOrder::active()->with('items.productType.group')->get()
            ->map(fn($po) => [
                'id'         => $po->id,
                'po_number'  => $po->po_number,
                'buyer_name' => $po->buyer_name,
                'items'      => $po->items->map(fn($i) => [
                    'id'              => $i->id,
                    'product_type_id' => $i->product_type_id,
                    'product_name'    => $i->productType->name,
                    'target_box'      => $i->target_box,
                    'target_weight'   => $i->target_weight_kg,
                    'weight_per_box'  => $i->effective_weight_per_box,
                ]),
            ]);
        return response()->json($pos);
    }
}