<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\MaterialGroup;
use App\Models\MaterialSubgroup;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Izin operasional. Catatan: rute tulis lama (PO, penerimaan, tahap, packing)
 * masih cukup auth saja; tes ini tidak mengklaim rute itu sudah diamankan.
 * Gate manage-operations dipasang ke rute minimum/CSV di job berikutnya.
 */
class OperationalPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $flags = []): User
    {
        $user = User::create([
            'name' => 'U', 'email' => uniqid().'@fishpro.test', 'password' => bcrypt('secret'),
        ]);
        $user->forceFill($flags)->save();

        return $user->fresh();
    }

    public function test_flag_default_false_dan_tidak_mass_assignable(): void
    {
        $user = User::create([
            'name' => 'X', 'email' => 'x@fishpro.test', 'password' => 'secret',
            'can_manage_operations' => true, 'can_view_audit' => true,
        ])->fresh();

        $this->assertFalse($user->can_manage_operations);
        $this->assertFalse($user->can_view_audit);
    }

    public function test_registrasi_mengabaikan_injeksi_flag(): void
    {
        $this->post('/register', [
            'name' => 'Baru', 'email' => 'baru@fishpro.test',
            'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
            'can_manage_operations' => 1, 'can_view_audit' => 1,
        ])->assertRedirect(route('dashboard'));

        $user = User::where('email', 'baru@fishpro.test')->firstOrFail();
        $this->assertFalse($user->can_manage_operations);
        $this->assertFalse($user->can_view_audit);
    }

    public function test_tiap_flag_berdiri_sendiri(): void
    {
        $biasa  = $this->user();
        $ops    = $this->user(['can_manage_operations' => true]);
        $audit  = $this->user(['can_view_audit' => true]);

        $this->assertFalse(Gate::forUser($biasa)->allows('manage-operations'));
        $this->assertFalse(Gate::forUser($biasa)->allows('view-audit'));
        $this->assertTrue(Gate::forUser($ops)->allows('manage-operations'));
        $this->assertFalse(Gate::forUser($ops)->allows('view-audit'));
        $this->assertTrue(Gate::forUser($audit)->allows('view-audit'));
        $this->assertFalse(Gate::forUser($audit)->allows('manage-operations'));
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get(route('riwayat.index', ['tab' => 'audit']))->assertRedirect(route('login'));
    }

    public function test_user_biasa_tidak_bisa_membaca_audit(): void
    {
        $biasa = $this->user();
        $this->actingAs($biasa);

        $po = $this->makePo('PO-RAHASIA-77');

        $this->get(route('riwayat.index', ['tab' => 'audit']))->assertForbidden();
        $this->get(route('riwayat.index', ['tab' => 'audit', 'audit_action' => 'po.create']))->assertForbidden();

        // Tab lama tetap terbuka, tanpa data audit sama sekali.
        $this->get(route('riwayat.index'))
            ->assertOk()
            ->assertViewHas('audits', null)
            ->assertDontSee('tab-audit')
            ->assertDontSee('po.create');

        $this->assertSame(1, AuditEvent::count());
    }

    public function test_halaman_403_mandiri_untuk_tamu_dan_user_ditolak_tanpa_query(): void
    {
        DB::enableQueryLog();
        try {
            $html = view('errors.403')->render();
            $this->assertStringContainsString('<html lang="id">', $html);
            $this->assertStringContainsString('403 — Akses ditolak', $html);
            $this->assertStringContainsString('Anda tidak memiliki izin', $html);
            $this->assertStringContainsString('--color-neutral-600:#4E525A', $html);
            $this->assertStringContainsString('--color-neutral-600:#C5CAD2', $html);
            $this->assertSame([], DB::getQueryLog());

            $this->actingAs($this->user());
            DB::flushQueryLog();
            $this->get(route('riwayat.index', ['tab' => 'audit']))
                ->assertForbidden()
                ->assertSee('403 — Akses ditolak')
                ->assertSee('Anda tidak memiliki izin')
                ->assertDontSee('id="sidebar"', false);
            $this->assertSame([], DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }

    public function test_pemegang_view_audit_melihat_tab(): void
    {
        $this->actingAs($this->user(['can_view_audit' => true]));
        $this->makePo('PO-AUDIT-1');

        $this->get(route('riwayat.index', ['tab' => 'audit']))
            ->assertOk()
            ->assertSee('tab-audit')
            ->assertSee('po.create');
    }

    public function test_aktor_dari_server_bukan_dari_request(): void
    {
        $user = $this->user();
        $lain = $this->user();
        $this->actingAs($user);

        $group = MaterialGroup::create(['name' => 'YF', 'unit' => 'kg', 'color' => '#2563eb']);
        $sub   = MaterialSubgroup::create(['material_group_id' => $group->id, 'name' => 'YF W', 'code' => 'YFW']);
        $sup   = Supplier::create(['code' => 'NJY', 'name' => 'UD Nelayan']);
        $po    = PurchaseOrder::create([
            'po_number' => 'PO-1', 'buyer_name' => 'B', 'order_date' => today(),
            'deadline' => today()->addDay(), 'status' => 'active', 'created_by' => $user->id,
        ]);

        $this->post(route('receiving.store'), [
            'purchase_order_id' => $po->id, 'material_subgroup_id' => $sub->id, 'supplier_id' => $sup->id,
            'weight_kg' => 50, 'container_type' => 'Keranjang', 'container_count' => 1,
            'received_date' => today()->toDateString(),
            'actor_id' => $lain->id, 'created_by' => $lain->id,
        ])->assertRedirect();

        $this->assertGreaterThan(0, AuditEvent::count());
        $this->assertSame([$user->id], AuditEvent::pluck('actor_id')->unique()->values()->all());
    }

    public function test_tidak_ada_rute_mutasi_audit(): void
    {
        foreach (app('router')->getRoutes() as $route) {
            $this->assertStringNotContainsString('audit', $route->uri());
        }
    }

    private function makePo(string $number): void
    {
        $this->post(route('po.store'), [
            'po_number' => $number, 'buyer_name' => 'Buyer',
            'order_date' => today()->toDateString(), 'deadline' => today()->addDays(3)->toDateString(),
            'items' => [['product_type_id' => $this->productType(), 'target_weight_kg' => 10, 'target_box' => 2]],
        ])->assertRedirect(route('po.index'));
    }

    private function productType(): int
    {
        $g = \App\Models\ProductGroup::create(['name' => 'Ikan '.uniqid(), 'color' => '#2563eb']);

        return \App\Models\ProductType::create([
            'product_group_id' => $g->id, 'name' => 'STEAK '.uniqid(), 'code' => strtoupper(substr(uniqid(), -5)),
        ])->id;
    }
}
