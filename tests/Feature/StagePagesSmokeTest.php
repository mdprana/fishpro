<?php

namespace Tests\Feature;

use App\Models\MaterialGroup;
use App\Models\MaterialSubgroup;
use App\Models\ProcessStage;
use App\Models\ProductGroup;
use App\Models\ProductionBatch;
use App\Models\ProductType;
use App\Models\PurchaseOrder;
use App\Models\Receiving;
use App\Models\Supplier;
use App\Models\User;
use App\Services\BatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StagePagesSmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private PurchaseOrder $po;
    private ProductionBatch $bahan;
    private ProductType $steak;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Operator', 'email' => 'op@fishpro.test', 'password' => bcrypt('secret'),
        ]);
        $this->actingAs($this->user);

        $this->seed(\Database\Seeders\ProcessStageSeeder::class);

        $supplier = Supplier::create(['code' => 'NJY', 'name' => 'UD Nelayan Jaya']);
        $group    = MaterialGroup::create(['name' => 'YF LOIN', 'unit' => 'kg', 'color' => '#2563eb']);
        $subgroup = MaterialSubgroup::create([
            'material_group_id' => $group->id, 'name' => 'YF LOIN EROPA WARNA', 'code' => 'YFW',
        ]);
        $pg = ProductGroup::create(['name' => 'Ikan', 'color' => '#2563eb']);
        $this->steak = ProductType::create(['product_group_id' => $pg->id, 'name' => 'YF STEAK', 'code' => 'YFSTK']);

        $this->po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-001', 'buyer_name' => 'Buyer',
            'order_date' => today(), 'deadline' => today()->addDays(10),
            'status' => 'active', 'created_by' => $this->user->id,
        ]);

        $this->bahan = app(BatchService::class)->createFromReceiving(Receiving::create([
            'material_subgroup_id' => $subgroup->id,
            'purchase_order_id'    => $this->po->id,
            'supplier_id'          => $supplier->id,
            'weight_kg'            => 100,
            'container_type'       => 'Keranjang',
            'container_count'      => 1,
            'received_date'        => today(),
            'created_by'           => $this->user->id,
        ]))->first();
    }

    public function test_setiap_tahap_punya_halaman_daftar_dan_form(): void
    {
        foreach (ProcessStage::all() as $stage) {
            $this->get(route('stage.index', $stage->code))->assertOk()->assertSee($stage->name);
            $this->get(route('stage.create', $stage->code))->assertOk()->assertSee('Bahan Masuk');
        }
    }

    public function test_route_record_tidak_tertangkap_wildcard_stage(): void
    {
        // /stage/record/{id} harus mengarah ke detail sesi, bukan dianggap
        // sebagai tahap berkode "record".
        $this->get('/stage/record/999')->assertNotFound();
    }

    public function test_menyimpan_sesi_potong_lewat_form(): void
    {
        $response = $this->post(route('stage.store', ProcessStage::CUTTING), [
            'purchase_order_id' => $this->po->id,
            'process_date'      => today()->toDateString(),
            'operator_name'     => 'Tim A',
            'inputs'  => [['batch_id' => $this->bahan->id, 'input_weight_kg' => 100]],
            'outputs' => [['output_type' => 'utama', 'output_weight_kg' => 72]],
            'wastes'  => [['waste_category' => 'kepala', 'output_weight_kg' => 28]],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('batch_stage_records', [
            'record_code' => 'CUT-' . today()->format('ymd') . '-0001',
            'yield_pct'   => 72.00,
        ]);

        $this->get($response->headers->get('Location'))
            ->assertOk()
            ->assertSee('Bahan Masuk')
            ->assertSee('Asal Tiap Hasil');
    }

    public function test_trimming_menolak_sesi_tanpa_catatan_limbah(): void
    {
        $this->from(route('stage.create', ProcessStage::TRIMMING))
            ->post(route('stage.store', ProcessStage::TRIMMING), [
                'purchase_order_id' => $this->po->id,
                'process_date'      => today()->toDateString(),
                'inputs'  => [['batch_id' => $this->bahan->id, 'input_weight_kg' => 50]],
                'outputs' => [['output_type' => 'utama', 'product_type_id' => $this->steak->id, 'output_weight_kg' => 45]],
                // limbah sengaja dikosongkan
            ])
            ->assertSessionHasErrors('wastes');

        $this->assertDatabaseCount('batch_stage_records', 0);
    }

    public function test_trimming_wajib_memilih_tipe_produk(): void
    {
        $this->from(route('stage.create', ProcessStage::TRIMMING))
            ->post(route('stage.store', ProcessStage::TRIMMING), [
                'purchase_order_id' => $this->po->id,
                'process_date'      => today()->toDateString(),
                'inputs'  => [['batch_id' => $this->bahan->id, 'input_weight_kg' => 50]],
                'outputs' => [['output_type' => 'utama', 'output_weight_kg' => 45]],
                'wastes'  => [['waste_category' => 'kulit', 'output_weight_kg' => 5]],
            ])
            ->assertSessionHasErrors('outputs.0.product_type_id');
    }

    public function test_api_input_tahap_hanya_menawarkan_hasil_tahap_sebelumnya(): void
    {
        // Potong adalah tahap pertama: inputnya bahan baku dari penerimaan.
        $this->getJson(route('api.stage.inputs', ProcessStage::CUTTING) . '?po_id=' . $this->po->id)
            ->assertOk()->assertJsonCount(1)
            ->assertJsonFragment(['batch_number' => $this->bahan->batch_number]);

        // Trimming belum punya bahan karena belum ada sesi potong.
        $this->getJson(route('api.stage.inputs', ProcessStage::TRIMMING) . '?po_id=' . $this->po->id)
            ->assertOk()->assertJsonCount(0);
    }

    public function test_halaman_standar_yield_tampil_dan_versi_lama_tertutup(): void
    {
        $this->get(route('master.yield-standards'))->assertOk()->assertSee('Standar Yield');

        $stage = ProcessStage::where('code', ProcessStage::TRIMMING)->firstOrFail();

        $this->post(route('master.yield-standards.store'), [
            'process_stage_id' => $stage->id,
            'min_yield_pct'    => 85,
            'effective_from'   => today()->subDays(10)->toDateString(),
        ])->assertRedirect();

        $this->post(route('master.yield-standards.store'), [
            'process_stage_id' => $stage->id,
            'min_yield_pct'    => 88,
            'effective_from'   => today()->toDateString(),
        ])->assertRedirect();

        // Versi lama ditutup, bukan dihapus, jadi dua baris harus tetap ada.
        $this->assertDatabaseCount('yield_standards', 2);
        $this->assertDatabaseHas('yield_standards', [
            'min_yield_pct' => 85.00,
            'effective_to'  => today()->subDay()->toDateString(),
        ]);
        $this->assertDatabaseHas('yield_standards', [
            'min_yield_pct' => 88.00,
            'effective_to'  => null,
        ]);
    }
}
