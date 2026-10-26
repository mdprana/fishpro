<?php
namespace App\Http\Controllers;

use App\Models\Receiving;
use App\Models\MaterialGroup;
use App\Models\MaterialSubgroup;
use App\Models\RawMaterialStock;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\BatchService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class ReceivingController extends Controller
{
    const CONTAINER_SUGGESTIONS = [
        'Box Polos','Keranjang','Karung','Drum','Peti Kayu',
        'Styrofoam Box','Plastik Bag','Tray','Ember','Wadah Lainnya',
    ];

    public function __construct(private readonly BatchService $batches)
    {
    }

    public function index(): View
    {
        $stocks = RawMaterialStock::with('subgroup.group')->get();

        return view('receiving.index', compact('stocks'));
    }


 public function create()
{
    $groups = MaterialGroup::with([
        'subgroups.yieldEstimates.productType'
    ])->get();

    $groupData = $groups->map(function ($g) {
        return [
            'id' => $g->id,
            'name' => $g->name,
            'color' => $g->color,

            'subgroups' => $g->subgroups->map(function ($s) {
                return [
                    'id' => $s->id,
                    'name' => $s->name,
                    'stock' => $s->current_stock ?? 0,
                    'total_pct' => $s->total_yield_pct ?? 0,

                    'yields' => $s->yieldEstimates->map(function ($ye) {
                        return [
                            'product' => optional($ye->productType)->name,
                            'color' => optional($ye->productType)->effective_color ?? '#0d6efd',
                            // Kolomnya bernama `percentage`; `yield_percentage` tidak pernah ada
                            // sehingga panel estimasi yield di form selalu menampilkan 0.
                            'percentage' => (float) ($ye->percentage ?? 0),
                        ];
                    })->values(),
                ];
            })->values(),
        ];
    })->values();

    return view('receiving.create', [
        'groups' => $groups,
        'groupData' => $groupData,
        'activePOs' => PurchaseOrder::active()->orderBy('po_number')->get(),
        'suppliers' => Supplier::active()->orderBy('code')->get(),
        'containerSuggests' => self::CONTAINER_SUGGESTIONS,
    ]);
}

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            // PO wajib: permintaan pabrik adalah barang masuk terhubung langsung ke nomor PO.
            'purchase_order_id'    => 'required|exists:purchase_orders,id',
            'material_subgroup_id' => 'required|exists:material_subgroups,id',
            'supplier_id'          => 'required|exists:suppliers,id',
            'weight_kg'            => 'required|numeric|min:0.01',
            'container_type'       => 'required|string|max:100',
            'container_count'      => 'required|integer|min:1',
            'split_per_container'  => 'nullable|boolean',
            'doc_number'           => 'nullable|string|max:100',
            'vehicle_number'       => 'nullable|string|max:30',
            'received_date'        => 'required|date',
            'unload_start'         => 'nullable|date_format:H:i',
            'unload_end'           => 'nullable|date_format:H:i',
            'temperature_c'        => 'nullable|numeric|between:-50,50',
            'notes'                => 'nullable|string|max:500',
        ]);

        $validated['split_per_container'] = $request->boolean('split_per_container');
        $validated['created_by']          = auth()->id();

        $batches = DB::transaction(function () use ($validated) {
            $receiving = Receiving::create($validated);

            // Batch Number Induk + label QR diterbitkan di sini; stok bahan baku
            // ikut dihitung ulang dari saldo batch, bukan ditambah manual.
            return $this->batches->createFromReceiving($receiving);
        });

        $sub    = MaterialSubgroup::find($validated['material_subgroup_id']);
        $jumlah = $batches->count();
        $msg    = "Bahan baku {$sub->name} {$validated['weight_kg']} kg diterima. "
                . ($jumlah > 1
                    ? "{$jumlah} lot wadah dibuat. Cetak labelnya sekarang."
                    : "Batch induk {$batches->first()->batch_number} dibuat. Cetak labelnya sekarang.");

        return redirect()
            ->route('batch.labels', ['ids' => $batches->pluck('id')->implode(',')])
            ->with('success', $msg);
    }

    /**
     * Membatalkan penerimaan. Bukan sekadar menghapus baris: seluruh batch
     * turunannya ikut dibatalkan lewat baris reversal di ledger, dan ditolak
     * bila isinya sudah terlanjur dipakai produksi.
     */
    public function destroy(Receiving $receiving): RedirectResponse
    {
        $name = $receiving->subgroup->name;

        try {
            DB::transaction(function () use ($receiving) {
                $this->batches->voidReceivingBatches($receiving, 'Penerimaan dihapus operator');
                $receiving->delete();
            });
        } catch (\RuntimeException $e) {
            return back()->withErrors(['receiving' => $e->getMessage()]);
        }

        return redirect()->route('receiving.index')
            ->with('success', "Penerimaan {$name} dibatalkan. Batch terkait ikut dibatalkan dan stok disesuaikan.");
    }

    public function stockCheck(Request $request): JsonResponse
    {
        $stock = RawMaterialStock::where('material_subgroup_id', $request->subgroup_id)->first();
        $sub   = MaterialSubgroup::with('yieldEstimates.productType')->find($request->subgroup_id);

        return response()->json([
            'current_stock_kg' => (float)($stock?->current_stock_kg ?? 0),
            'total_yield_pct'  => (float)($sub?->total_yield_pct ?? 0),
            'yield_breakdown'  => $sub?->estimated_outputs->map(fn($o) => [
                'name'       => $o['product_type']->name,
                'color'      => $o['product_type']->effective_color,
                'percentage' => $o['percentage'],
            ]) ?? [],
        ]);
    }

    public function importCsv(Request $request): RedirectResponse
    {
        $request->validate(['csv_file' => 'required|file|mimes:csv,txt|max:2048']);

        $rows    = array_map('str_getcsv', file($request->file('csv_file')->getPathname()));
        $headers = array_map('trim', array_shift($rows));
        $imported = 0; $errors = [];

        foreach ($rows as $i => $row) {
            if (count($row) < 2) continue;
            $baris = $i + 2; // +1 header, +1 karena manusia menghitung dari 1
            $data  = array_combine($headers, array_map('trim', $row));

            $sub = MaterialSubgroup::where('name','like','%'.($data['subgroup']??'').'%')->first();
            if (!$sub) { $errors[] = "Baris {$baris}: subgroup '".($data['subgroup'] ?? '')."' tidak ditemukan"; continue; }

            $wt = (float)($data['weight_kg']??0);
            if ($wt <= 0) { $errors[] = "Baris {$baris}: berat tidak valid"; continue; }

            // Kolom CSV `supplier` boleh diisi kode (NJY) maupun nama lengkap.
            $supplierKey = trim($data['supplier'] ?? '');
            $supplier    = $supplierKey === '' ? null : Supplier::where('code', Supplier::normalizeCode($supplierKey))
                ->orWhere('name', $supplierKey)->first();

            if (!$supplier) {
                $errors[] = "Baris {$baris}: supplier '{$supplierKey}' tidak terdaftar di master";
                continue;
            }

            // Tanpa PO, batch tidak bisa dikaitkan ke pesanan mana pun.
            $po = PurchaseOrder::where('po_number', trim($data['po_number'] ?? ''))->first();
            if (!$po) {
                $errors[] = "Baris {$baris}: nomor PO '".($data['po_number'] ?? '')."' tidak ditemukan";
                continue;
            }

            try {
                DB::transaction(function () use ($sub, $wt, $data, $supplier, $po) {
                    $receiving = Receiving::create([
                        'material_subgroup_id' => $sub->id,
                        'purchase_order_id'    => $po->id,
                        'supplier_id'          => $supplier->id,
                        'weight_kg'            => $wt,
                        'container_type'       => $data['container_type'] ?? 'Box Polos',
                        'container_count'      => (int)($data['container_count'] ?? 1) ?: 1,
                        'split_per_container'  => filter_var($data['split_per_container'] ?? false, FILTER_VALIDATE_BOOL),
                        'received_date'        => $data['received_date'] ?? today()->format('Y-m-d'),
                        'batch_code'           => $data['batch_code'] ?? null,
                        'doc_number'           => $data['doc_number'] ?? null,
                        'created_by'           => auth()->id(),
                    ]);

                    $this->batches->createFromReceiving($receiving);
                });
                $imported++;
            } catch (\Throwable $e) {
                $errors[] = "Baris {$baris}: {$e->getMessage()}";
            }
        }

        return redirect()->route('receiving.index')
            ->with('success', "{$imported} baris diimport." . ($errors ? ' Error: '.implode(', ',$errors) : ''));
    }
}
