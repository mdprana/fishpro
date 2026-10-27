<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\MaterialGroup;
use App\Models\MaterialSubgroup;
use App\Models\PurchaseOrder;
use App\Models\RawMaterialStock;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OperationalAlertsTest extends TestCase
{
    use RefreshDatabase;

    private function user(bool $ops = false): User
    {
        $user = User::create(['name' => 'U', 'email' => uniqid().'@fishpro.test', 'password' => bcrypt('secret')]);
        $user->forceFill(['can_manage_operations' => $ops])->save();

        return $user->fresh();
    }

    private function sub(string $name, ?float $stock, ?string $minimum, bool $active = true, ?MaterialGroup $group = null): MaterialSubgroup
    {
        $group ??= MaterialGroup::firstOrCreate(['name' => 'Tuna'], ['unit' => 'kg', 'color' => '#2563eb']);
        $sub = MaterialSubgroup::create(['material_group_id' => $group->id, 'name' => $name, 'is_active' => $active]);
        $sub->forceFill(['minimum_stock_kg' => $minimum])->save();
        if ($stock !== null) {
            RawMaterialStock::create(['material_subgroup_id' => $sub->id, 'current_stock_kg' => $stock]);
        }

        return $sub->fresh();
    }

    private function stockAlerts(): \Illuminate\Support\Collection
    {
        return collect(app(DashboardService::class)->getSummary()['alerts'])->where('category', 'stok');
    }

    private function deadlineAlerts(): \Illuminate\Support\Collection
    {
        return collect(app(DashboardService::class)->getSummary()['alerts'])->where('category', 'deadline');
    }

    private function po(string $number, $deadline, string $status = 'active'): PurchaseOrder
    {
        return PurchaseOrder::create([
            'po_number' => $number, 'buyer_name' => 'Buyer', 'order_date' => '2020-01-01',
            'deadline' => $deadline, 'status' => $status,
            'created_by' => User::firstOrCreate(['email' => 'po@fishpro.test'], ['name' => 'PO', 'password' => 'secret'])->id,
        ]);
    }

    public function test_minimum_null_nol_positif_dan_kesetaraan(): void
    {
        $this->sub('Kosong Null', 5, null);
        $this->sub('Nol Pas', 0, '0');
        $this->sub('Sama', 25, '25');
        $this->sub('Di Atas', 25.01, '25');
        $this->sub('Di Bawah', 3, '25.5');
        $this->sub('Tanpa Baris Stok', null, '0');

        $alerts = $this->stockAlerts()->pluck('product')->sort()->values()->all();
        $this->assertSame(['Di Bawah', 'Nol Pas', 'Sama', 'Tanpa Baris Stok'], $alerts);

        $summary = collect(app(DashboardService::class)->getStockSummary())->keyBy('subgroup');
        $this->assertNull($summary['Kosong Null']['minimum_reached']);
        $this->assertNull($summary['Kosong Null']['minimum_stock_kg']);
        $this->assertFalse($summary['Di Atas']['minimum_reached']);
        $this->assertSame(0.0, $summary['Tanpa Baris Stok']['current_stock_kg']);
        $this->assertTrue($summary['Tanpa Baris Stok']['minimum_reached']);
    }

    public function test_tanpa_ambang_lama_10_50(): void
    {
        $this->sub('Tipis', 5, null);
        $this->sub('Sedang', 30, null);

        $this->assertCount(0, $this->stockAlerts());

        foreach (['app/Services/DashboardService.php', 'resources/views/receiving/index.blade.php',
                  'resources/views/dashboard/index.blade.php', 'resources/views/master/material_groups.blade.php'] as $file) {
            $this->assertDoesNotMatchRegularExpression('/<\s*(10|50)\b/', file_get_contents(base_path($file)), $file);
        }
    }

    public function test_halaman_menampilkan_stok_nol_dan_minimum_tanpa_menulis_db(): void
    {
        $this->actingAs($this->user());
        $this->sub('Tanpa Baris', null, '0');
        $this->sub('Belum Diatur', 7, null);
        $nonaktif = $this->sub('Sub Nonaktif', 0, '5', active: false);
        $grupMati = MaterialGroup::create(['name' => 'Grup Mati', 'unit' => 'kg', 'color' => '#000000', 'is_active' => false]);
        $this->sub('Di Grup Mati', 0, '5', group: $grupMati);

        $stockRows = RawMaterialStock::count();
        $updated   = MaterialSubgroup::max('updated_at');
        DB::enableQueryLog();

        $this->get(route('receiving.index'))->assertOk()
            ->assertSee('Tanpa Baris')->assertSee('0.0')->assertSee('Minimum: 0.00 kg')
            ->assertSee('Stok minimum tercapai')->assertSee('Minimum: Belum diatur')
            ->assertDontSee('Sub Nonaktif')->assertDontSee('Di Grup Mati');

        $this->get(route('dashboard'))->assertOk()
            ->assertSee('Tanpa Baris')->assertSee('Stok minimum tercapai')->assertSee('Belum diatur')
            ->assertDontSee('Sub Nonaktif')->assertDontSee('Di Grup Mati');

        $writes = collect(DB::getQueryLog())->pluck('query')
            ->filter(fn ($q) => preg_match('/^\s*(insert|update|delete)/i', $q));
        $this->assertCount(0, $writes);
        $this->assertSame($stockRows, RawMaterialStock::count());
        $this->assertSame($updated, MaterialSubgroup::max('updated_at'));
        $this->assertNotContains($nonaktif->name, $this->stockAlerts()->pluck('product')->all());
    }

    public function test_set_minimum_tercatat_audit_dan_noop_tanpa_event(): void
    {
        $ops = $this->user(true);
        $sub = $this->sub('Loin', 10, null);

        $this->actingAs($ops)->put(route('master.subgroups.update', $sub), [
            'name' => 'Loin', 'is_active' => 1, 'minimum_stock_kg' => '12.5',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('12.50', $sub->fresh()->minimum_stock_kg);
        $event = AuditEvent::sole();
        $this->assertSame('stock.minimum_changed', $event->action);
        $this->assertSame($ops->id, $event->actor_id);
        $this->assertSame(['minimum_stock_kg' => null], $event->before);
        $this->assertSame(['minimum_stock_kg' => '12.50'], $event->after);

        $this->put(route('master.subgroups.update', $sub), [
            'name' => 'Loin Baru', 'is_active' => 1, 'minimum_stock_kg' => '12.50',
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, AuditEvent::count());
        $this->assertSame('Loin Baru', $sub->fresh()->name);
    }

    public function test_absen_mempertahankan_kosong_menghapus(): void
    {
        $ops = $this->user(true);
        $sub = $this->sub('Loin', 10, '8');

        $this->actingAs($ops)->put(route('master.subgroups.update', $sub), ['name' => 'Loin 2', 'is_active' => 1])
            ->assertSessionHasNoErrors();
        $this->assertSame('8.00', $sub->fresh()->minimum_stock_kg);
        $this->assertSame(0, AuditEvent::count());

        $this->put(route('master.subgroups.update', $sub), ['name' => 'Loin 2', 'is_active' => 1, 'minimum_stock_kg' => ''])
            ->assertSessionHasNoErrors();
        $this->assertNull($sub->fresh()->minimum_stock_kg);
        $this->assertSame(['minimum_stock_kg' => null], AuditEvent::sole()->after);
    }

    public function test_nilai_tidak_valid_ditolak_tanpa_mutasi(): void
    {
        $ops = $this->user(true);
        $sub = $this->sub('Loin', 10, '8');
        $this->actingAs($ops);

        foreach (['-1', 'NaN', 'INF', '1e3', '1.234', '10000000000', '99999999999.99', 'abc', '5,5', '.5', '5.', '0x10'] as $bad) {
            $this->put(route('master.subgroups.update', $sub), ['name' => 'Ganti', 'is_active' => 1, 'minimum_stock_kg' => $bad])
                ->assertSessionHasErrors('minimum_stock_kg');
        }
        $this->put(route('master.subgroups.update', $sub), ['name' => 'Ganti', 'minimum_stock_kg' => ['1']])
            ->assertSessionHasErrors('minimum_stock_kg');

        $this->assertSame('8.00', $sub->fresh()->minimum_stock_kg);
        $this->assertSame('Loin', $sub->fresh()->name);
        $this->assertSame(0, AuditEvent::count());

        $this->put(route('master.subgroups.update', $sub), ['name' => 'Loin', 'is_active' => 1, 'minimum_stock_kg' => '9999999999.99'])
            ->assertSessionHasNoErrors();
        $this->assertSame('9999999999.99', $sub->fresh()->minimum_stock_kg);
    }

    public function test_create_subgroup_dengan_minimum(): void
    {
        $ops   = $this->user(true);
        $group = MaterialGroup::create(['name' => 'Tuna', 'unit' => 'kg', 'color' => '#2563eb']);

        $this->actingAs($ops)->post(route('master.subgroups.store'), [
            'material_group_id' => $group->id, 'name' => 'Baru', 'minimum_stock_kg' => '0',
        ])->assertSessionHasNoErrors();
        $sub = MaterialSubgroup::where('name', 'Baru')->sole();
        $this->assertSame('0.00', $sub->minimum_stock_kg);
        $this->assertSame(['minimum_stock_kg' => '0.00'], AuditEvent::sole()->after);

        $this->post(route('master.subgroups.store'), [
            'material_group_id' => $group->id, 'name' => 'Tanpa', 'minimum_stock_kg' => '',
        ])->assertSessionHasNoErrors();
        $this->assertNull(MaterialSubgroup::where('name', 'Tanpa')->sole()->minimum_stock_kg);
        $this->assertSame(1, AuditEvent::count());

        $this->post(route('master.subgroups.store'), [
            'material_group_id' => $group->id, 'name' => 'Salah', 'minimum_stock_kg' => '-2',
        ])->assertSessionHasErrors('minimum_stock_kg');
        $this->assertFalse(MaterialSubgroup::where('name', 'Salah')->exists());
    }

    public function test_user_biasa_ditolak_dan_kontrol_tersembunyi(): void
    {
        $biasa = $this->user();
        $group = MaterialGroup::create(['name' => 'Tuna', 'unit' => 'kg', 'color' => '#2563eb']);
        $sub   = $this->sub('Loin', 10, '8', group: $group);

        $this->put(route('master.subgroups.update', $sub), ['name' => 'X', 'minimum_stock_kg' => '1'])
            ->assertRedirect(route('login'));

        $this->actingAs($biasa)->put(route('master.subgroups.update', $sub), ['name' => 'X', 'minimum_stock_kg' => '1'])
            ->assertForbidden();
        $this->put(route('master.subgroups.update', $sub), ['name' => 'X', 'minimum_stock_kg' => ''])->assertForbidden();
        $this->post(route('master.subgroups.store'), [
            'material_group_id' => $group->id, 'name' => 'Y', 'minimum_stock_kg' => '1',
        ])->assertForbidden();
        $this->assertSame('8.00', $sub->fresh()->minimum_stock_kg);
        $this->assertSame('Loin', $sub->fresh()->name);
        $this->assertFalse(MaterialSubgroup::where('name', 'Y')->exists());

        $this->put(route('master.subgroups.update', $sub), ['name' => 'X', 'is_active' => 1])->assertSessionHasNoErrors();
        $this->assertSame('X', $sub->fresh()->name);
        $this->assertSame('8.00', $sub->fresh()->minimum_stock_kg);
        $this->assertSame(0, AuditEvent::count());

        $this->get(route('master.material-groups'))->assertOk()->assertDontSee('name="minimum_stock_kg"', false);
        $this->actingAs($this->user(true))->get(route('master.material-groups'))->assertOk()
            ->assertSee('name="minimum_stock_kg"', false);
    }

    public function test_deadline_kemarin_hari_ini_besok_di_awal_dan_akhir_hari(): void
    {
        foreach (['00:00:00', '23:59:59'] as $time) {
            PurchaseOrder::query()->forceDelete();
            $this->travelTo(Carbon::parse('2026-10-26 '.$time));
            $kemarin = $this->po('PO-KEMARIN', '2026-10-25');
            $hariIni = $this->po('PO-HARIINI', '2026-10-26');
            $this->po('PO-BESOK', '2026-10-27');
            $lama = $this->po('PO-LAMA', '2026-10-16', 'in_progress');

            $alerts = $this->deadlineAlerts()->keyBy('po_id');
            $this->assertCount(3, $alerts, $time);
            $this->assertSame('Terlambat 1 hari', $alerts[$kemarin->id]['message']);
            $this->assertSame('danger', $alerts[$kemarin->id]['type']);
            $this->assertSame('Jatuh tempo hari ini', $alerts[$hariIni->id]['message']);
            $this->assertSame('Terlambat 10 hari', $alerts[$lama->id]['message']);
        }
    }

    public function test_deadline_melintasi_bulan_tahun_kabisat(): void
    {
        foreach (['2026-10-31' => '2026-11-01', '2026-12-31' => '2027-01-01', '2028-02-28' => '2028-02-29', '2028-02-29' => '2028-03-01'] as $today => $deadline) {
            PurchaseOrder::query()->forceDelete();
            $po = $this->po('PO-'.$today, $deadline);

            $this->travelTo(Carbon::parse($today)->endOfDay());
            $this->assertCount(0, $this->deadlineAlerts(), $today);

            $this->travelTo(Carbon::parse($deadline)->startOfDay());
            $this->assertSame('Jatuh tempo hari ini', $this->deadlineAlerts()->sole()['message']);

            $this->travelTo(Carbon::parse($deadline)->addDay()->startOfDay());
            $this->assertSame('Terlambat 1 hari', $this->deadlineAlerts()->sole()['message']);
            $this->assertSame($po->id, $this->deadlineAlerts()->sole()['po_id']);
        }
    }

    public function test_deadline_mengecualikan_selesai_batal_terhapus_dan_po_tanpa_item_sekali(): void
    {
        $this->travelTo(Carbon::parse('2026-10-26 12:00'));
        $this->po('PO-SELESAI', '2026-10-20', 'completed');
        $this->po('PO-BATAL', '2026-10-20', 'cancelled');
        $this->po('PO-HAPUS', '2026-10-20')->delete();
        $kosong = $this->po('PO-KOSONG', '2026-10-26');

        $summary = app(DashboardService::class)->getSummary();
        $alerts  = collect($summary['alerts']);
        $this->assertCount(1, $alerts);
        $this->assertSame($kosong->id, $alerts->sole()['po_id']);
        $this->assertSame('deadline', $alerts->sole()['category']);
        $this->assertSame(1, $summary['total_alerts']);

        $this->actingAs($this->user())->get(route('dashboard'))->assertOk()
            ->assertSee('Jatuh tempo hari ini')->assertSee(route('po.show', $kosong))
            ->assertDontSee('PO-SELESAI')->assertDontSee('PO-BATAL')->assertDontSee('PO-HAPUS');
    }
}
