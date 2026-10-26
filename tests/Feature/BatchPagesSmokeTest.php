<?php

namespace Tests\Feature;

use App\Models\MaterialGroup;
use App\Models\MaterialSubgroup;
use App\Models\ProductionBatch;
use App\Models\PurchaseOrder;
use App\Models\Receiving;
use App\Models\Supplier;
use App\Models\User;
use App\Services\BatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Memastikan halaman batch, label, dan telusur benar-benar merender,
 * bukan hanya logikanya yang benar.
 */
class BatchPagesSmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    /** @var Collection<int, ProductionBatch> */
    private Collection $batches;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Operator', 'email' => 'op@fishpro.test', 'password' => bcrypt('secret'),
        ]);
        $this->actingAs($this->user);

        $supplier = Supplier::create(['code' => 'NJY', 'name' => 'UD Nelayan Jaya']);
        $group    = MaterialGroup::create(['name' => 'YF LOIN', 'unit' => 'kg', 'color' => '#2563eb']);
        $subgroup = MaterialSubgroup::create([
            'material_group_id' => $group->id, 'name' => 'YF LOIN EROPA WARNA', 'code' => 'YFW',
        ]);
        $po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-001', 'buyer_name' => 'Buyer',
            'order_date' => today(), 'deadline' => today()->addDays(7),
            'status' => 'active', 'created_by' => $this->user->id,
        ]);

        $receiving = Receiving::create([
            'material_subgroup_id' => $subgroup->id,
            'purchase_order_id'    => $po->id,
            'supplier_id'          => $supplier->id,
            'weight_kg'            => 400,
            'container_type'       => 'Keranjang',
            'container_count'      => 4,
            'split_per_container'  => true,
            'received_date'        => today(),
            'created_by'           => $this->user->id,
        ]);

        $this->batches = app(BatchService::class)->createFromReceiving($receiving);
    }

    public function test_daftar_batch_tampil(): void
    {
        $this->get(route('batch.index'))
            ->assertOk()
            ->assertSee('Batch &amp; Lot', false)
            ->assertSee($this->batches->first()->batch_number);
    }

    public function test_detail_batch_menampilkan_ledger(): void
    {
        $batch = $this->batches->first();

        $this->get(route('batch.show', $batch))
            ->assertOk()
            ->assertSee($batch->batch_number)
            ->assertSee($batch->qr_code)
            ->assertSee('Riwayat Pergerakan');
    }

    public function test_lembar_label_memuat_semua_batch_terpilih(): void
    {
        $ids = $this->batches->pluck('id')->implode(',');

        $response = $this->get(route('batch.labels', ['ids' => $ids]))->assertOk();

        foreach ($this->batches as $batch) {
            $response->assertSee($batch->batch_number);
            // QR digambar di browser dari atribut data-qr berisi URL telusur.
            $response->assertSee($batch->qr_url, false);
        }

        // Pencetakan ditandai supaya bisa dilihat label mana yang belum pernah keluar.
        $this->assertNotNull($this->batches->first()->fresh()->label_printed_at);
    }

    public function test_label_tanpa_pilihan_ditolak(): void
    {
        $this->get(route('batch.labels'))->assertNotFound();
    }

    public function test_scan_qr_membuka_halaman_telusur(): void
    {
        $batch = $this->batches->first();

        $this->get(route('trace.batch', $batch->qr_code))
            ->assertOk()
            ->assertSee($batch->batch_number)
            ->assertSee('NJY');
    }

    public function test_resolve_menerima_nomor_batch_maupun_url_hasil_scan(): void
    {
        $batch = $this->batches->first();

        $this->post(route('trace.resolve'), ['code' => $batch->batch_number])
            ->assertRedirect(route('trace.batch', $batch->qr_code));

        // Kamera HP membaca URL penuh, bukan hanya tokennya.
        $this->post(route('trace.resolve'), ['code' => $batch->qr_url])
            ->assertRedirect(route('trace.batch', $batch->qr_code));
    }

    public function test_kode_asing_ditolak_dengan_pesan_jelas(): void
    {
        $this->from(route('trace.index'))
            ->post(route('trace.resolve'), ['code' => 'TIDAKADA1'])
            ->assertRedirect(route('trace.index'))
            ->assertSessionHasErrors('code');
    }

    public function test_api_lookup_mengembalikan_sisa_batch(): void
    {
        $batch = $this->batches->first();

        $this->getJson(route('api.batch.lookup', $batch->qr_code))
            ->assertOk()
            ->assertJsonFragment([
                'batch_number'  => $batch->batch_number,
                'supplier_code' => 'NJY',
                'available_kg'  => 100.0,
            ]);
    }

    public function test_api_available_hanya_batch_yang_masih_berisi(): void
    {
        // Induk sudah dikosongkan saat dipecah, jadi hanya 4 wadah yang tersisa.
        $this->getJson(route('api.batch.available'))
            ->assertOk()
            ->assertJsonCount(4);
    }
}
