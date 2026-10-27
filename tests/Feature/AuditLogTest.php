<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\BatchMovement;
use App\Models\BatchStageRecord;
use App\Models\MaterialGroup;
use App\Models\MaterialSubgroup;
use App\Models\PackingBox;
use App\Models\ProcessStage;
use App\Models\ProductGroup;
use App\Models\ProductionBatch;
use App\Models\ProductType;
use App\Models\PurchaseOrder;
use App\Models\Receiving;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AuditService;
use App\Services\PackingService;
use App\Services\StageService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Supplier $supplier;
    private MaterialSubgroup $subgroup;
    private PurchaseOrder $po;
    private ProductType $steak;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create(['name' => 'Operator Rahasia', 'email' => 'op@fishpro.test', 'password' => bcrypt('secret')]);
        $this->user->forceFill(['can_view_audit' => true])->save();
        $this->actingAs($this->user);

        $this->seed(\Database\Seeders\ProcessStageSeeder::class);

        $this->supplier = Supplier::create(['code' => 'NJY', 'name' => 'UD Nelayan Jaya']);
        $group = MaterialGroup::create(['name' => 'YF LOIN', 'unit' => 'kg', 'color' => '#2563eb']);
        $this->subgroup = MaterialSubgroup::create(['material_group_id' => $group->id, 'name' => 'YF W', 'code' => 'YFW']);

        $pg = ProductGroup::create(['name' => 'Ikan', 'color' => '#2563eb']);
        $this->steak = ProductType::create(['product_group_id' => $pg->id, 'name' => 'YF STEAK', 'code' => 'YFSTK']);

        $this->po = PurchaseOrder::create([
            'po_number' => 'PO-A-1', 'buyer_name' => 'Buyer', 'order_date' => today()->subDays(10),
            'deadline' => today()->addDays(10), 'status' => 'active', 'created_by' => $this->user->id,
        ]);
        $this->po->items()->create(['product_type_id' => $this->steak->id, 'target_weight_kg' => 100, 'target_box' => 20, 'weight_per_box_kg' => 5]);
    }

    private function terima(float $kg, int $wadah = 1, bool $split = false, int $hariLalu = 5)
    {
        $this->post(route('receiving.store'), [
            'purchase_order_id' => $this->po->id, 'material_subgroup_id' => $this->subgroup->id,
            'supplier_id' => $this->supplier->id, 'weight_kg' => $kg, 'container_type' => 'Keranjang',
            'container_count' => $wadah, 'split_per_container' => $split ? 1 : 0,
            'received_date' => today()->subDays($hariLalu)->toDateString(),
            'doc_number' => 'DOC-SECRET', 'notes' => 'catatan bebas',
        ])->assertRedirect();

        return Receiving::latest('id')->firstOrFail();
    }

    private function lotSteak(float $kg): ProductionBatch
    {
        $batch = ProductionBatch::where('receiving_id', $this->terima($kg)->id)->firstOrFail();

        return app(StageService::class)->record(ProcessStage::where('code', ProcessStage::CUTTING)->firstOrFail(), [
            'purchase_order_id' => $this->po->id,
            'process_date'      => today()->subDays(2)->toDateString(),
            'operator_name'     => 'Tim Potong',
            'inputs'  => [['batch_id' => $batch->id, 'input_weight_kg' => $kg]],
            'outputs' => [['output_type' => 'utama', 'product_type_id' => $this->steak->id, 'output_weight_kg' => $kg - 10]],
            'wastes'  => [['waste_category' => 'kepala', 'output_weight_kg' => 10]],
        ])['batches']->first();
    }

    public function test_penerimaan_dan_split_tercatat_dengan_allowlist(): void
    {
        $r = $this->terima(100.5, 2, true, 7);

        $create = AuditEvent::where('action', 'receiving.create')->sole();
        $this->assertSame($this->user->id, (int) $create->actor_id);
        $this->assertNull($create->before);
        $this->assertSame([
            'purchase_order_id' => $this->po->id, 'material_subgroup_id' => $this->subgroup->id,
            'supplier_id' => $this->supplier->id, 'weight_kg' => '100.50', 'container_count' => 2,
            'split_per_container' => true, 'received_date' => today()->subDays(7)->toDateString(), 'deleted' => false,
        ], $create->after);

        // receipt induk + 2 anak + consume induk = 4 movement, masing-masing satu event.
        $moves = BatchMovement::count();
        $this->assertSame(4, $moves);
        $this->assertSame($moves, AuditEvent::where('action', 'stock.movement')->count());
        $this->assertSame($moves, AuditEvent::whereNotNull('movement_id')->distinct()->count('movement_id'));

        $receipt = AuditEvent::where('movement_id', BatchMovement::where('movement_type', 'receipt')->value('id'))->sole();
        $this->assertSame(['current_weight_kg' => '0.000'], $receipt->before);
        $this->assertSame('100.500', $receipt->after['current_weight_kg']);
        $this->assertSame('100.500', $receipt->after['weight_kg']);
        $this->assertSame($r->id, $receipt->after['receiving_id']);

        // Waktu tulis audit = sekarang, bukan tanggal bisnis yang dimundurkan.
        $this->assertTrue($receipt->recorded_local->isToday());
        $this->assertFalse(BatchMovement::where('movement_type', 'receipt')->first()->occurred_at->isToday());

        $json = AuditEvent::all()->map(fn ($e) => json_encode([$e->before, $e->after]))->implode(' ');
        foreach (['DOC-SECRET', 'catatan bebas', 'Operator Rahasia', 'op@fishpro.test', 'password', 'note'] as $rahasia) {
            $this->assertStringNotContainsString($rahasia, $json);
        }
    }

    public function test_void_penerimaan(): void
    {
        $r = $this->terima(50);
        $this->delete(route('receiving.destroy', $r))->assertRedirect();

        $void = AuditEvent::where('action', 'receiving.void')->sole();
        $this->assertFalse($void->before['deleted']);
        $this->assertTrue($void->after['deleted']);
        $this->assertSame($r->id, (int) $void->entity_id);
    }

    public function test_tahap_dan_void_tahap(): void
    {
        $lot = $this->lotSteak(100);

        $stage = AuditEvent::where('action', 'stage.create')->sole();
        $this->assertSame('100.000', $stage->after['total_input_kg']);
        $this->assertSame('90.000', $stage->after['total_output_kg']);
        $this->assertSame('completed', $stage->after['status']);
        $this->assertArrayNotHasKey('operator_name', $stage->after);

        $record = BatchStageRecord::findOrFail($lot->batch_stage_record_id);
        $this->post(route('stage.void', $record), ['void_reason' => 'salah input'])->assertRedirect();

        $void = AuditEvent::where('action', 'stage.void')->sole();
        $this->assertSame('completed', $void->before['status']);
        $this->assertSame('voided', $void->after['status']);
        $this->assertSame(BatchMovement::count(), AuditEvent::where('action', 'stock.movement')->count());
    }

    public function test_packing_bulk_timbang_koreksi_void_tanpa_duplikat(): void
    {
        $lot = $this->lotSteak(60);
        $svc = app(PackingService::class);

        $svc->bulkGenerate($this->po, $this->steak, [['batch_id' => $lot->id, 'kg' => 15]], 3, 5, ['packing_date' => today()->toDateString()]);
        $single = $svc->weighSingleBox($this->po, $this->steak, [['batch_id' => $lot->id, 'kg' => 5.25]], 5.25);

        $this->assertSame(4, PackingBox::count());
        $this->assertSame(4, AuditEvent::where('action', 'box.create')->count());
        $this->assertSame(4, AuditEvent::where('action', 'box.create')->distinct()->count('entity_id'));
        $this->assertSame('5.250', AuditEvent::where('action', 'box.create')->where('entity_id', $single->id)->sole()->after['net_weight_kg']);

        $svc->adjustBoxWeight($single, 5.25);
        $this->assertSame(0, AuditEvent::where('action', 'box.adjust')->count(), 'no-op tidak boleh menulis event');

        $svc->adjustBoxWeight($single, 4.8);
        $adj = AuditEvent::where('action', 'box.adjust')->sole();
        $this->assertSame('5.250', $adj->before['net_weight_kg']);
        $this->assertSame('4.800', $adj->after['net_weight_kg']);

        $svc->voidBox($single, 'rusak');
        $void = AuditEvent::where('action', 'box.void')->sole();
        $this->assertSame(['packed', 'voided'], [$void->before['status'], $void->after['status']]);
        $this->assertSame(BatchMovement::count(), AuditEvent::where('action', 'stock.movement')->count());
    }

    public function test_po_create_update_delete(): void
    {
        $payload = fn (array $extra = []) => $extra + [
            'buyer_name' => 'Buyer', 'order_date' => today()->toDateString(),
            'deadline' => today()->addDays(5)->toDateString(),
            'items' => [['product_type_id' => $this->steak->id, 'target_weight_kg' => 10, 'target_box' => 2]],
        ];

        $this->post(route('po.store'), $payload(['po_number' => 'PO-NEW']))->assertRedirect();
        $po = PurchaseOrder::where('po_number', 'PO-NEW')->firstOrFail();
        $create = AuditEvent::where('action', 'po.create')->sole();
        $this->assertSame('active', $create->after['status']);
        $this->assertSame([['product_type_id' => $this->steak->id, 'target_weight_kg' => '10.00', 'target_box' => 2]], $create->after['items']);
        $this->assertArrayNotHasKey('buyer_name', $create->after);

        $item = $po->items()->first();
        $same = $payload(['status' => 'active', 'items' => [['id' => $item->id, 'product_type_id' => $this->steak->id, 'target_weight_kg' => 10, 'target_box' => 2]]]);
        $this->put(route('po.update', $po), $same)->assertRedirect();
        $this->assertSame(0, AuditEvent::where('action', 'po.update')->count(), 'no-op tidak boleh menulis event');

        $this->put(route('po.update', $po), ['status' => 'in_progress', 'deadline' => today()->addDays(9)->toDateString()] + $same)->assertRedirect();
        $upd = AuditEvent::where('action', 'po.update')->sole();
        $this->assertSame(['active', 'in_progress'], [$upd->before['status'], $upd->after['status']]);

        $this->delete(route('po.destroy', $po))->assertRedirect();
        $del = AuditEvent::where('action', 'po.delete')->sole();
        $this->assertSame([false, true], [$del->before['deleted'], $del->after['deleted']]);
    }

    public function test_gagal_tulis_audit_membatalkan_seluruh_operasi(): void
    {
        AuditEvent::creating(function (AuditEvent $e) {
            if ($e->action === 'stock.movement') {
                throw new \RuntimeException('audit gagal');
            }
        });

        $receivings = Receiving::withTrashed()->count();

        try {
            $this->withoutExceptionHandling()->post(route('receiving.store'), [
                'purchase_order_id' => $this->po->id, 'material_subgroup_id' => $this->subgroup->id,
                'supplier_id' => $this->supplier->id, 'weight_kg' => 40, 'container_type' => 'Keranjang',
                'container_count' => 1, 'received_date' => today()->toDateString(),
            ]);
            $this->fail('Harus gagal');
        } catch (\RuntimeException $e) {
            $this->assertSame('audit gagal', $e->getMessage());
        }

        $this->assertSame($receivings, Receiving::withTrashed()->count());
        $this->assertSame(0, ProductionBatch::count());
        $this->assertSame(0, BatchMovement::count());
        $this->assertSame(0, AuditEvent::count());
    }

    public function test_rollback_luar_setelah_event_tertulis(): void
    {
        try {
            DB::transaction(function () {
                $po = PurchaseOrder::findOrFail($this->po->id);
                app(AuditService::class)->record('po.update', $po, ['status' => 'active'], ['status' => 'completed']);
                $this->assertSame(1, AuditEvent::count());
                throw new \RuntimeException('batal');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame(0, AuditEvent::count());
    }

    public function test_movement_dan_audit_ditolak_tanpa_transaksi(): void
    {
        // RefreshDatabase membungkus tes dalam transaksi; keluar sementara untuk membuktikan penolakan.
        $level = DB::transactionLevel();
        $batch = ProductionBatch::where('receiving_id', $this->terima(10)->id)->firstOrFail();

        // Rollback (bukan commit) supaya data tes tidak bocor ke tes lain.
        for ($i = 0; $i < $level; $i++) {
            DB::rollBack();
        }

        try {
            $this->assertSame(0, DB::transactionLevel());
            $this->assertThrows(fn () => $batch->applyMovement('stage_consume', 'out', 1), \LogicException::class);
            $this->assertThrows(
                fn () => app(AuditService::class)->record('po.update', $this->po, ['status' => 'a'], ['status' => 'b']),
                \LogicException::class
            );
            $this->assertSame(0, BatchMovement::count());
            $this->assertSame(0, AuditEvent::count());
        } finally {
            for ($i = 0; $i < $level; $i++) {
                DB::beginTransaction();
            }
        }
    }

    public function test_trigger_menolak_update_dan_delete(): void
    {
        $this->terima(20);
        $event    = AuditEvent::firstOrFail();
        $original = $event->getAttributes();

        $this->assertThrows(fn () => $event->update(['action' => 'po.delete']), \LogicException::class);
        $this->assertThrows(fn () => $event->delete(), \LogicException::class);
        $this->assertThrows(fn () => DB::table('audit_events')->update(['actor_id' => 999]), QueryException::class);
        $this->assertThrows(fn () => DB::table('audit_events')->where('id', $event->id)->delete(), QueryException::class);
        $this->assertThrows(fn () => DB::table('audit_events')->truncate(), QueryException::class);

        $this->assertSame($original, AuditEvent::findOrFail($event->id)->getAttributes());
    }

    public function test_hapus_sumber_dan_user_tidak_mengubah_snapshot(): void
    {
        $r = $this->terima(30);

        // User tanpa baris bisnis (FK lama created_by menahan penghapusan user lain).
        $other = User::create(['name' => 'Z', 'email' => 'z@fishpro.test', 'password' => 'x']);
        $this->actingAs($other);
        DB::transaction(fn () => app(AuditService::class)->record('po.update', $this->po, ['status' => 'active'], ['status' => 'in_progress']));
        $this->actingAs($this->user);

        $before = AuditEvent::orderBy('id')->get()->map->getAttributes()->all();
        $this->assertSame($other->id, (int) AuditEvent::where('action', 'po.update')->value('actor_id'));

        BatchMovement::query()->getQuery()->where('receiving_id', $r->id)->update(['receiving_id' => null]);
        ProductionBatch::where('receiving_id', $r->id)->update(['receiving_id' => null]);
        $r->forceDelete();
        $other->delete();

        $after = AuditEvent::orderBy('id')->get()->map->getAttributes()->all();
        $this->assertSame($before, $after);
    }

    public function test_tab_audit_filter_paginasi_escaping(): void
    {
        $this->terima(10);
        $this->terima(12);
        $total = AuditEvent::count();

        $this->get(route('riwayat.index', ['tab' => 'audit', 'audit_action' => 'receiving.create']))
            ->assertOk()
            ->assertViewHas('audits', fn ($p) => $p->total() === 2 && $p->every(fn ($e) => $e->action === 'receiving.create'))
            ->assertSee('Audit terstruktur tersedia sejak migrasi ini')
            ->assertSee(config('app.timezone'));

        $this->get(route('riwayat.index', ['tab' => 'audit', 'audit_entity' => 'batch']))
            ->assertViewHas('audits', fn ($p) => $p->total() === $total - 2);

        $this->get(route('riwayat.index', ['tab' => 'audit', 'audit_actor' => $this->user->id + 1]))
            ->assertViewHas('audits', fn ($p) => $p->total() === 0);

        $this->get(route('riwayat.index', ['tab' => 'audit', 'audit_from' => today()->addDay()->toDateString()]))
            ->assertViewHas('audits', fn ($p) => $p->total() === 0);
        $this->get(route('riwayat.index', ['tab' => 'audit', 'audit_from' => today()->toDateString(), 'audit_to' => today()->toDateString()]))
            ->assertViewHas('audits', fn ($p) => $p->total() === $total);

        foreach (['audit_entity' => 'users', 'audit_action' => 'drop', 'audit_actor' => 'abc', 'audit_from' => '2026-13-01'] as $k => $v) {
            $this->get(route('riwayat.index', ['tab' => 'audit', $k => $v]))->assertSessionHasErrors($k);
        }

        // Paginasi 30 baris, urutan id menurun, query filter dipertahankan.
        foreach (range(1, 8) as $i) {
            $this->terima(10 + $i, 2, true);
        }
        $page = $this->get(route('riwayat.index', ['tab' => 'audit', 'audit_entity' => 'batch']))->viewData('audits');
        $this->assertSame(30, $page->count());
        $this->assertSame($page->pluck('id')->sortDesc()->values()->all(), $page->pluck('id')->all());
        $this->assertStringContainsString('audit_entity=batch', $page->nextPageUrl());

        // Nama aktor berisi HTML ditampilkan sebagai teks.
        $this->user->forceFill(['name' => '<script>alert(1)</script>'])->save();
        $this->get(route('riwayat.index', ['tab' => 'audit']))
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_tab_lama_dan_trace_tetap_jalan(): void
    {
        $this->terima(10);
        $this->get(route('riwayat.index'))->assertOk()->assertSee('Ledger Batch')->assertSee('Penerimaan (Masuk)');
        $batch = ProductionBatch::firstOrFail();
        $this->get(route('trace.batch', $batch->qr_code))->assertOk();
    }
}
