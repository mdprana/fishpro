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
use App\Models\YieldStandard;
use App\Services\BatchService;
use App\Services\PackingService;
use App\Services\StageService;
use App\Services\YieldService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Supplier $njy;
    private Supplier $ocf;
    private MaterialSubgroup $subgroup;
    private PurchaseOrder $po;
    private ProductType $steak;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Admin', 'email' => 'admin@fishpro.test', 'password' => bcrypt('secret'),
        ]);
        $this->actingAs($this->user);

        $this->seed(\Database\Seeders\ProcessStageSeeder::class);

        $this->njy = Supplier::create(['code' => 'NJY', 'name' => 'UD Nelayan Jaya']);
        $this->ocf = Supplier::create(['code' => 'OCF', 'name' => 'PT Ocean Fresh']);

        $group = MaterialGroup::create(['name' => 'YF LOIN', 'unit' => 'kg', 'color' => '#2563eb']);
        $this->subgroup = MaterialSubgroup::create([
            'material_group_id' => $group->id, 'name' => 'YF LOIN EROPA WARNA', 'code' => 'YFW',
        ]);

        $pg = ProductGroup::create(['name' => 'Ikan', 'color' => '#2563eb']);
        $this->steak = ProductType::create(['product_group_id' => $pg->id, 'name' => 'YF STEAK', 'code' => 'YFSTK']);

        $this->po = PurchaseOrder::create([
            'po_number' => 'PO-2026-010', 'buyer_name' => 'Buyer',
            'order_date' => today()->subDays(10), 'deadline' => today()->addDays(10),
            'status' => 'active', 'created_by' => $this->user->id,
        ]);
        $this->po->items()->create([
            'product_type_id' => $this->steak->id,
            'target_weight_kg' => 500, 'target_box' => 100, 'weight_per_box_kg' => 5,
        ]);
    }

    private function terima(float $kg, Supplier $supplier, int $hariLalu): ProductionBatch
    {
        return app(BatchService::class)->createFromReceiving(Receiving::create([
            'material_subgroup_id' => $this->subgroup->id,
            'purchase_order_id'    => $this->po->id,
            'supplier_id'          => $supplier->id,
            'weight_kg'            => $kg,
            'container_type'       => 'Keranjang',
            'container_count'      => 1,
            'received_date'        => today()->subDays($hariLalu),
            'created_by'           => $this->user->id,
        ]))->first();
    }

    /** Jalankan rantai penuh lalu packing, supaya laporan punya isi. */
    private function produksiPenuh(): void
    {
        $a = $this->terima(600, $this->njy, 8);
        $b = $this->terima(400, $this->ocf, 7);

        $cut = app(StageService::class)->record(ProcessStage::where('code', 'cutting')->first(), [
            'purchase_order_id' => $this->po->id,
            'process_date'      => today()->subDays(4)->toDateString(),
            'inputs' => [
                ['batch_id' => $a->id, 'input_weight_kg' => 600],
                ['batch_id' => $b->id, 'input_weight_kg' => 400],
            ],
            'outputs' => [['output_type' => 'utama', 'output_weight_kg' => 720]],
            'wastes'  => [['waste_category' => 'kepala', 'output_weight_kg' => 280]],
        ])['batches']->first();

        $trim = app(StageService::class)->record(ProcessStage::where('code', 'trimming')->first(), [
            'purchase_order_id' => $this->po->id,
            'process_date'      => today()->subDays(3)->toDateString(),
            'inputs'  => [['batch_id' => $cut->id, 'input_weight_kg' => 720]],
            'outputs' => [['output_type' => 'utama', 'product_type_id' => $this->steak->id, 'output_weight_kg' => 620]],
            'wastes'  => [['waste_category' => 'kulit', 'output_weight_kg' => 100]],
        ])['batches']->first();

        $lot = app(StageService::class)->record(ProcessStage::where('code', 'steak')->first(), [
            'purchase_order_id' => $this->po->id,
            'process_date'      => today()->subDays(2)->toDateString(),
            'inputs'  => [['batch_id' => $trim->id, 'input_weight_kg' => 620]],
            'outputs' => [['output_type' => 'utama', 'product_type_id' => $this->steak->id, 'output_weight_kg' => 600]],
        ])['batches']->first();

        app(PackingService::class)->bulkGenerate(
            $this->po, $this->steak,
            [['batch_id' => $lot->id, 'kg' => 500]],
            100, 5,
            ['packing_date' => today()->toDateString()]
        );
    }

    public function test_rekap_bahan_baku_menampilkan_asal_per_supplier(): void
    {
        $this->produksiPenuh();

        $this->get(route('report.raw-material'))
            ->assertOk()
            ->assertSee('Per Supplier')
            ->assertSee('Per Batch Induk')
            ->assertSee('NJY')
            ->assertSee('OCF')
            ->assertSee('UD Nelayan Jaya');
    }

    public function test_rekap_bahan_baku_bisa_diexport_csv(): void
    {
        $this->produksiPenuh();

        $response = $this->get(route('report.raw-material.csv'));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $isi = $response->streamedContent();
        $this->assertStringContainsString('REKAP BAHAN BAKU', $isi);
        $this->assertStringContainsString('NJY', $isi);
        $this->assertStringContainsString('PER BATCH INDUK', $isi);
    }

    public function test_dashboard_recovery_menghitung_packing_dibagi_bahan_baku(): void
    {
        $this->produksiPenuh();

        $summary = app(YieldService::class)->summary();

        $this->assertEqualsWithDelta(1000.0, $summary['bahan_kg'], 0.01);
        $this->assertEqualsWithDelta(500.0,  $summary['packing_kg'], 0.01);
        $this->assertEqualsWithDelta(50.0,   $summary['recovery_pct'], 0.01);

        $this->get(route('report.yield'))
            ->assertOk()
            ->assertSee('Recovery')
            ->assertSee('Yield per Tahap')
            ->assertSee('50.0%');
    }

    public function test_alert_muncul_untuk_sesi_di_bawah_standar(): void
    {
        YieldStandard::create([
            'material_subgroup_id' => $this->subgroup->id,
            'process_stage_id'     => ProcessStage::where('code', 'cutting')->value('id'),
            'scope'                => 'stage',
            'min_yield_pct'        => 80,   // aktual potong hanya 72%
            'effective_from'       => today()->subMonth(),
            'is_active'            => true,
        ]);

        $this->produksiPenuh();

        $alerts = app(YieldService::class)->alerts();
        $this->assertCount(1, $alerts);
        $this->assertSame('below', $alerts->first()['level']);
        $this->assertEqualsWithDelta(8.0, $alerts->first()['selisih_pct'], 0.1);

        $this->get(route('report.yield'))
            ->assertOk()
            ->assertSee('Alert Penyusutan Melebihi Toleransi')
            ->assertSee('Di bawah standar');
    }

    public function test_daftar_po_menampilkan_tanggal_tiap_tahap(): void
    {
        $this->produksiPenuh();

        $this->get(route('po.index'))
            ->assertOk()
            ->assertSee('Bongkar')
            ->assertSee('Trimming')
            ->assertSee('Packing')
            ->assertSee(today()->subDays(8)->format('d/m/y'))   // tgl bongkar pertama
            ->assertSee(today()->subDays(4)->format('d/m/y'))   // tgl potong
            ->assertSee(today()->subDays(3)->format('d/m/y'));  // tgl trimming
    }

    public function test_detail_po_menampilkan_timeline_dan_batch(): void
    {
        $this->produksiPenuh();

        $this->get(route('po.show', $this->po))
            ->assertOk()
            ->assertSee('Timeline Proses')
            ->assertSee('Batch Bahan Baku')
            ->assertSee('Recovery')
            ->assertSee('NJY');
    }
}
