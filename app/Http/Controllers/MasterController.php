<?php
namespace App\Http\Controllers;

use App\Models\MaterialGroup;
use App\Models\MaterialSubgroup;
use App\Models\ProductGroup;
use App\Models\ProductType;
use App\Models\RawMaterialStock;
use App\Models\YieldEstimate;
use App\Models\YieldEstimateLog;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use App\Services\AuditService;

class MasterController extends Controller
{
    public function __construct(private readonly AuditService $audit)
    {
    }

    // ════════════════════════════════════════════════════════
    // GROUP BAHAN BAKU
    // ════════════════════════════════════════════════════════
    public function materialGroups(): View
    {
        $groups = MaterialGroup::with(['subgroups.stock','subgroups.yieldEstimates.productType'])
            ->orderBy('name')->get();
        $productTypes = ProductType::active()->with('group')->orderBy('name')->get();
        return view('master.material_groups', compact('groups','productTypes'));
    }

    public function storeMaterialGroup(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100|unique:material_groups,name',
            'unit'        => 'required|string|max:20',
            'color'       => 'required|string|max:7',
            'description' => 'nullable|string|max:300',
        ]);
        $group = MaterialGroup::create($data);
        return back()->with('success', "Group bahan baku {$group->name} ditambahkan.");
    }

    public function updateMaterialGroup(Request $request, MaterialGroup $materialGroup): RedirectResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100|unique:material_groups,name,'.$materialGroup->id,
            'unit'        => 'required|string|max:20',
            'color'       => 'required|string|max:7',
            'description' => 'nullable|string|max:300',
            'is_active'   => 'boolean',
        ]);
        $materialGroup->update($data);
        return back()->with('success', "Group {$materialGroup->name} diperbarui.");
    }

    public function destroyMaterialGroup(MaterialGroup $materialGroup): RedirectResponse
    {
        $materialGroup->update(['is_active' => false]);
        return back()->with('success', "Group {$materialGroup->name} dinonaktifkan.");
    }

    // ════════════════════════════════════════════════════════
    // SUBGROUP BAHAN BAKU
    // ════════════════════════════════════════════════════════
    public function storeSubgroup(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'material_group_id' => 'required|exists:material_groups,id',
            'name'              => 'required|string|max:150',
        ] + $this->minimumRule($request));

        $sub = DB::transaction(function () use ($request, $data) {
            $sub = MaterialSubgroup::create($data);
            RawMaterialStock::create(['material_subgroup_id' => $sub->id, 'current_stock_kg' => 0]);

            if ($request->filled('minimum_stock_kg')) {
                $sub->forceFill(['minimum_stock_kg' => $data['minimum_stock_kg']])->save();
                $this->audit->record('stock.minimum_changed', $sub, null, $this->audit->snapshot($sub->fresh()));
            }

            return $sub;
        });

        return back()->with('success', "Subgroup {$sub->name} ditambahkan.");
    }

    public function updateSubgroup(Request $request, MaterialSubgroup $subgroup): RedirectResponse
    {
        $data = $request->validate([
            'name'      => 'required|string|max:150',
            'is_active' => 'boolean',
        ] + $this->minimumRule($request));

        DB::transaction(function () use ($request, $data, $subgroup) {
            $locked = MaterialSubgroup::whereKey($subgroup->id)->lockForUpdate()->firstOrFail();
            $before = $this->audit->snapshot($locked);

            $locked->update(collect($data)->only(['name', 'is_active'])->all());

            // Field tidak dikirim = nilai lama dipertahankan; dikirim kosong = dihapus.
            if ($request->exists('minimum_stock_kg')) {
                $locked->forceFill(['minimum_stock_kg' => $data['minimum_stock_kg']])->save();
                $this->audit->record('stock.minimum_changed', $locked, $before, $this->audit->snapshot($locked->fresh()));
            }
        });

        return back()->with('success', "Subgroup {$subgroup->name} diperbarui.");
    }

    /** Aturan minimum stok; mengirim field ini butuh gate manage-operations. */
    private function minimumRule(Request $request): array
    {
        if (! $request->exists('minimum_stock_kg')) {
            return [];
        }

        Gate::authorize('manage-operations');

        // Maks DECIMAL(12,2): 10 digit bulat, 2 desimal; menolak negatif, NaN, INF, eksponen.
        return ['minimum_stock_kg' => ['nullable', 'string', 'regex:/^\d{1,10}(\.\d{1,2})?$/']];
    }

    public function destroySubgroup(MaterialSubgroup $subgroup): RedirectResponse
    {
        $subgroup->update(['is_active' => false]);
        return back()->with('success', "Subgroup {$subgroup->name} dinonaktifkan.");
    }

    // ════════════════════════════════════════════════════════
    // ESTIMASI YIELD (Subgroup -> Produk)
    // ════════════════════════════════════════════════════════
    public function saveYieldEstimates(Request $request, MaterialSubgroup $subgroup): RedirectResponse
    {
        $request->validate([
            'product_type_id'   => 'required|array',
            'product_type_id.*' => 'exists:product_types,id',
            'percentage'        => 'required|array',
            'percentage.*'      => 'numeric|min:0|max:100',
        ]);

        DB::transaction(function () use ($request, $subgroup) {
            // Estimasi yield adalah pembanding "estimasi vs aktual" di laporan.
            // Dulu disimpan dengan delete-all lalu create ulang, sehingga
            // perubahan hari ini diam-diam mengubah tafsir laporan bulan lalu
            // tanpa jejak. Sekarang: sinkron per pasangan + simpan snapshot.
            $sebelum = $subgroup->yieldEstimates()->with('productType')->get()
                ->map(fn ($ye) => [
                    'product_type_id' => $ye->product_type_id,
                    'product'         => $ye->productType?->name,
                    'percentage'      => (float) $ye->percentage,
                ])->values()->all();

            $dipertahankan = [];

            foreach ($request->product_type_id as $i => $ptId) {
                $pct = (float) ($request->percentage[$i] ?? 0);
                if ($pct <= 0) continue;

                YieldEstimate::updateOrCreate(
                    ['material_subgroup_id' => $subgroup->id, 'product_type_id' => $ptId],
                    ['percentage' => $pct]
                );
                $dipertahankan[] = $ptId;
            }

            // Hanya pasangan yang benar-benar dihilangkan operator yang dihapus.
            $subgroup->yieldEstimates()
                ->whereNotIn('product_type_id', $dipertahankan ?: [0])
                ->delete();

            $sesudah = $subgroup->yieldEstimates()->with('productType')->get()
                ->map(fn ($ye) => [
                    'product_type_id' => $ye->product_type_id,
                    'product'         => $ye->productType?->name,
                    'percentage'      => (float) $ye->percentage,
                ])->values()->all();

            if ($sebelum !== $sesudah) {
                YieldEstimateLog::create([
                    'material_subgroup_id' => $subgroup->id,
                    'previous'             => $sebelum,
                    'snapshot'             => $sesudah,
                    'created_by'           => auth()->id(),
                ]);
            }
        });

        // Validasi: setiap produk bebas 0-100%, tidak harus total 100%
        $saved = $subgroup->yieldEstimates()->count();
        return back()->with('success', "Estimasi yield {$subgroup->name} disimpan. {$saved} produk dikonfigurasi (masing-masing 0-100%).");
    }

    // ════════════════════════════════════════════════════════
    // GROUP PRODUK
    // ════════════════════════════════════════════════════════
    public function productGroups(): View
    {
        $groups = ProductGroup::with(['productTypes' => fn($q) => $q->withCount(['poItems','stageOutputs','packingBoxes'])])
            ->orderBy('name')->get();
        return view('master.product_groups', compact('groups'));
    }

    public function storeProductGroup(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'  => 'required|string|max:100|unique:product_groups,name',
            'color' => 'required|string|max:7',
        ]);
        $group = ProductGroup::create($data);
        return back()->with('success', "Group produk {$group->name} ditambahkan.");
    }

    public function updateProductGroup(Request $request, ProductGroup $productGroup): RedirectResponse
    {
        $data = $request->validate([
            'name'      => 'required|string|max:100|unique:product_groups,name,'.$productGroup->id,
            'color'     => 'required|string|max:7',
            'is_active' => 'boolean',
        ]);
        $productGroup->update($data);
        return back()->with('success', "Group produk {$productGroup->name} diperbarui.");
    }

    // ════════════════════════════════════════════════════════
    // TIPE PRODUK (di bawah Group Produk)
    // ════════════════════════════════════════════════════════
    public function storeProductType(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_group_id' => 'required|exists:product_groups,id',
            'name'             => 'required|string|max:100|unique:product_types,name',
            'color'            => 'nullable|string|max:7',
        ]);
        $data['color'] = $data['color'] ?: null; // null = pakai warna grup
        ProductType::create($data);
        return back()->with('success', "Tipe produk {$data['name']} ditambahkan.");
    }

    public function updateProductType(Request $request, ProductType $productType): RedirectResponse
    {
        $data = $request->validate([
            'product_group_id' => 'required|exists:product_groups,id',
            'name'             => 'required|string|max:100|unique:product_types,name,'.$productType->id,
            'color'            => 'nullable|string|max:7',
            'is_active'        => 'boolean',
        ]);
        $data['color'] = $data['color'] ?: null;
        $productType->update($data);
        return back()->with('success', "Tipe produk diperbarui.");
    }

    // ════════════════════════════════════════════════════════
    // API
    // ════════════════════════════════════════════════════════

    /** API: produk dikelompokkan per grup, untuk dropdown bertingkat */
    public function productsGrouped(): JsonResponse
    {
        $groups = ProductGroup::active()->with(['productTypes' => fn($q) => $q->active()->orderBy('name')])->orderBy('name')->get();
        return response()->json($groups->map(fn($g) => [
            'id'    => $g->id,
            'name'  => $g->name,
            'color' => $g->color,
            'products' => $g->productTypes->map(fn($pt) => [
                'id'    => $pt->id,
                'name'  => $pt->name,
                'color' => $pt->effective_color,
            ]),
        ]));
    }

    /** API: estimasi yield untuk subgroup tertentu + stok */
    public function subgroupYields(MaterialSubgroup $subgroup): JsonResponse
    {
        return response()->json([
            'stock'   => $subgroup->current_stock,
            'total_pct' => $subgroup->total_yield_pct,
            'outputs' => $subgroup->estimated_outputs->map(fn($o) => [
                'product_type_id' => $o['product_type']->id,
                'name'            => $o['product_type']->name,
                'color'           => $o['product_type']->effective_color,
                'percentage'      => $o['percentage'],
                'estimated_kg'    => $o['estimated_kg'],
            ]),
        ]);
    }
}
