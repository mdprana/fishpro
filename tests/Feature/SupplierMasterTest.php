<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierMasterTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create([
            'name' => 'Admin', 'email' => 'admin@fishpro.test', 'password' => bcrypt('secret'),
        ]);
    }

    public function test_halaman_supplier_tampil(): void
    {
        Supplier::create(['code' => 'NJY', 'name' => 'UD Nelayan Jaya', 'city' => 'Banyuwangi']);

        $this->actingAs($this->user)
            ->get(route('master.suppliers'))
            ->assertOk()
            ->assertSee('NJY')
            ->assertSee('UD Nelayan Jaya');
    }

    public function test_menambah_supplier_menormalkan_kode(): void
    {
        $this->actingAs($this->user)->post(route('master.suppliers.store'), [
            'code' => 'nj-y ',   // huruf kecil + karakter yang tidak boleh masuk nomor batch
            'name' => 'UD Nelayan Jaya',
        ])->assertRedirect();

        $this->assertDatabaseHas('suppliers', ['code' => 'NJY', 'is_active' => true]);
    }

    public function test_kode_supplier_tidak_boleh_kembar(): void
    {
        Supplier::create(['code' => 'NJY', 'name' => 'Yang Pertama']);

        $this->actingAs($this->user)
            ->from(route('master.suppliers'))
            ->post(route('master.suppliers.store'), ['code' => 'NJY', 'name' => 'Yang Kedua'])
            ->assertSessionHasErrors('code');

        $this->assertSame(1, Supplier::count());
    }

    public function test_menghapus_supplier_hanya_menonaktifkan(): void
    {
        $supplier = Supplier::create(['code' => 'NJY', 'name' => 'UD Nelayan Jaya']);

        $this->actingAs($this->user)
            ->delete(route('master.suppliers.destroy', $supplier))
            ->assertRedirect();

        // Kode supplier sudah tertanam di nomor batch lama, jadi barisnya harus tetap ada.
        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'is_active' => false]);
    }

    public function test_lookup_hanya_mengembalikan_supplier_aktif(): void
    {
        Supplier::create(['code' => 'NJY', 'name' => 'Aktif', 'is_active' => true]);
        Supplier::create(['code' => 'OLD', 'name' => 'Nonaktif', 'is_active' => false]);

        $response = $this->actingAs($this->user)->getJson(route('api.supplier.lookup'));

        $response->assertOk()->assertJsonCount(1)->assertJsonFragment(['code' => 'NJY']);
    }
}
