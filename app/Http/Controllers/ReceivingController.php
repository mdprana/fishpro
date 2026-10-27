<?php
namespace App\Http\Controllers;

use App\Models\Receiving;
use App\Models\MaterialGroup;
use App\Models\MaterialSubgroup;
use App\Models\RawMaterialStock;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\AuditService;
use App\Services\BatchService;
use App\Services\ReceivingCsv;
use App\Models\ReceivingImport;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;
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

    public function __construct(
        private readonly BatchService $batches,
        private readonly AuditService $audit,
    ) {
    }

    public function index(): View
    {
        $subgroups = MaterialSubgroup::forStockView()->get();

        return view('receiving.index', compact('subgroups'));
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
        $validated = $request->validate(ReceivingCsv::rules() + [
            // PO wajib: permintaan pabrik adalah barang masuk terhubung langsung ke nomor PO.
            'purchase_order_id'    => 'required|exists:purchase_orders,id',
            'material_subgroup_id' => 'required|exists:material_subgroups,id',
            'supplier_id'          => 'required|exists:suppliers,id',
            'vehicle_number'       => 'nullable|string|max:30',
            'unload_start'         => 'nullable|date_format:H:i',
            'unload_end'           => 'nullable|date_format:H:i',
            'temperature_c'        => 'nullable|numeric|between:-50,50',
            'notes'                => 'nullable|string|max:500',
        ]);

        $validated['split_per_container'] = $request->boolean('split_per_container');
        $validated['created_by']          = auth()->id();

        $batches = DB::transaction(function () use ($validated) {
            $receiving = Receiving::create($validated);
            $this->audit->record('receiving.create', $receiving, null, $this->audit->snapshot($receiving->fresh()));

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
                $locked = Receiving::whereKey($receiving->id)->lockForUpdate()->firstOrFail();
                $before = $this->audit->snapshot($locked);

                $this->batches->voidReceivingBatches($locked, 'Penerimaan dihapus operator');
                $locked->delete();

                $this->audit->record('receiving.void', $locked, $before, $this->audit->snapshot($locked));
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

    public function importCsv(Request $request, ReceivingCsv $csv): View
    {
        Gate::authorize('manage-operations');
        $request->validate(['csv_file' => 'required|file|extensions:csv,txt|max:1024']);
        $stream = fopen($request->file('csv_file')->getPathname(), 'rb');
        try {
            $bytes = stream_get_contents($stream, ReceivingCsv::MAX_BYTES + 1);
        } finally {
            fclose($stream);
        }
        $preview = $csv->parse($bytes);
        $envelope = $preview['errors'] ? null : $csv->envelope($bytes, (int) $request->user()->id, $preview);

        return view('receiving.preview', compact('preview', 'envelope'));
    }

    public function csvTemplate()
    {
        Gate::authorize('manage-operations');
        return response(implode(',', ReceivingCsv::HEADERS)."\nPO-CONTOH,KODE-SUB,KODE-SUP,100.50,Keranjang,2,true,2026-10-27,DOK-001\n", 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="penerimaan-contoh.csv"',
        ]);
    }

    public function commitCsv(Request $request, ReceivingCsv $csv): RedirectResponse
    {
        Gate::authorize('manage-operations');
        $envelope = $request->input('envelope');
        $request->request->remove('envelope');
        if ((int) $request->server('CONTENT_LENGTH', 0) > ReceivingCsv::MAX_ENVELOPE + 65536) {
            throw ValidationException::withMessages(['csv_file' => 'Konfirmasi terlalu besar.']);
        }
        [$meta, $bytes] = $csv->open($envelope, (int) $request->user()->id);
        $line = 1;
        for ($attempt = 0; $attempt < 5; $attempt++) {
        try {
            $receipt = DB::transaction(function () use ($csv, $meta, $bytes, &$line) {
                $existing = ReceivingImport::where('nonce', $meta['nonce'])->first();
                if ($existing) {
                    return $this->matchingReceipt($existing, $meta);
                }
                $preview = $csv->parse($bytes, true);
                if ($preview['errors'] || !hash_equals($meta['digest'], $preview['digest']) || !hash_equals($meta['references'], $preview['references'])) {
                    throw ValidationException::withMessages(['csv_file' => 'Referensi/isi berubah atau tidak valid. Buat pratinjau baru.']);
                }
                if (ReceivingImport::where('content_hash', $meta['digest'])->exists()) {
                    throw ValidationException::withMessages(['csv_file' => 'Isi CSV sudah pernah diimpor.']);
                }
                $receipt = ReceivingImport::create(['actor_id' => auth()->id(), 'nonce' => $meta['nonce'],
                    'content_hash' => $meta['digest'], 'row_count' => count($preview['rows']), 'receiving_ids' => []]);
                $ids = [];
                foreach ($preview['rows'] as $row) {
                    $line = $row['line'];
                    $receiving = Receiving::create($row['data'] + ['created_by' => auth()->id()]);
                    $this->audit->record('receiving.create', $receiving, null, $this->audit->snapshot($receiving->fresh()));
                    $this->batches->createFromReceiving($receiving);
                    $ids[] = (int) $receiving->id;
                }
                DB::table('receiving_imports')->where('id', $receipt->id)->update(['receiving_ids' => json_encode($ids, JSON_THROW_ON_ERROR)]);
                return $receipt->fresh();
            }, 3);
            break;
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            if ($e instanceof QueryException) {
                if (DB::getDriverName() === 'sqlite' && in_array((int) ($e->errorInfo[1] ?? 0), [5, 6], true) && $attempt < 4) {
                    usleep(100000 * ($attempt + 1));
                    continue;
                }
                $existing = ReceivingImport::where('nonce', $meta['nonce'])->first();
                if ($existing) {
                    $receipt = $this->matchingReceipt($existing, $meta);
                    return $this->importSuccess($receipt);
                }
                if (ReceivingImport::where('content_hash', $meta['digest'])->exists()) {
                    throw ValidationException::withMessages(['csv_file' => 'Isi CSV sudah pernah diimpor.']);
                }
            }
            throw ValidationException::withMessages(['csv_file' => "Baris {$line}: kolom CSV gagal disimpan. Tidak ada perubahan; coba lagi."]);
        }
        }
        return $this->importSuccess($receipt);
    }

    private function matchingReceipt(ReceivingImport $receipt, array $meta): ReceivingImport
    {
        if ($receipt->actor_id !== (int) auth()->id() || !hash_equals($receipt->content_hash, $meta['digest'])) {
            throw ValidationException::withMessages(['csv_file' => 'Isi CSV sudah pernah diimpor.']);
        }
        return $receipt;
    }

    private function importSuccess(ReceivingImport $receipt): RedirectResponse
    {
        return redirect()->route('receiving.index')->with('success', "{$receipt->row_count} baris berhasil diimpor. Konfirmasi ulang tidak menambah penerimaan.");
    }
}
