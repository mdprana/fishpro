<?php

namespace Tests\Feature;

use App\Models\BatchStageLink;
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
use App\Services\StageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class StageYieldTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Supplier $njy;
    private Supplier $ocf;
    private MaterialSubgroup $subgroup;
    private PurchaseOrder $po;
    private ProductType $steak;
    private ProcessStage $cutting;
    private ProcessStage $trimming;
    private ProcessStage $steakStage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Operator', 'email' => 'op@fishpro.test', 'password' => bcrypt('secret'),
        ]);
        $this->actingAs($this->user);

        $this->seed(\Database\Seeders\ProcessStageSeeder::class);
        $this->cutting    = ProcessStage::where('code', ProcessStage::CUTTING)->firstOrFail();
        $this->trimming   = ProcessStage::where('code', ProcessStage::TRIMMING)->firstOrFail();
        $this->steakStage = ProcessStage::where('code', ProcessStage::STEAK)->firstOrFail();

        $this->njy = Supplier::create(['code' => 'NJY', 'name' => 'UD Nelayan Jaya']);
        $this->ocf = Supplier::create(['code' => 'OCF', 'name' => 'PT Ocean Fresh']);

        $group = MaterialGroup::create(['name' => 'YF LOIN', 'unit' => 'kg', 'color' => '#2563eb']);
        $this->subgroup = MaterialSubgroup::create([
            'material_group_id' => $group->id, 'name' => 'YF LOIN EROPA WARNA', 'code' => 'YFW',
        ]);

        $pg = ProductGroup::create(['name' => 'Ikan', 'color' => '#2563eb']);
        $this->steak = ProductType::create([
            'product_group_id' => $pg->id, 'name' => 'YF STEAK', 'code' => 'YFSTK',
        ]);

        $this->po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-001', 'buyer_name' => 'Buyer',
            'order_date' => today(), 'deadline' => today()->addDays(10),
            'status' => 'active', 'created_by' => $this->user->id,
        ]);
    }

    /** @return Collection<int, ProductionBatch> */
    private function terima(float $kg, Supplier $supplier): Collection
    {
        return app(BatchService::class)->createFromReceiving(Receiving::create([
            'material_subgroup_id' => $this->subgroup->id,
            'purchase_order_id'    => $this->po->id,
            'supplier_id'          => $supplier->id,
            'weight_kg'            => $kg,
            'container_type'       => 'Keranjang',
            'container_count'      => 1,
            'received_date'        => today(),
            'created_by'           => $this->user->id,
        ]));
    }

    public function test_potong_mengurangi_batch_asal_dan_menerbitkan_lot_anak(): void
    {
        $bahan = $this->terima(100, $this->njy)->first();

        $hasil = app(StageService::class)->record($this->cutting, [
            'purchase_order_id' => $this->po->id,
            'process_date'      => today()->toDateString(),
            'operator_name'     => 'Tim A',
            'inputs'  => [['batch_id' => $bahan->id, 'input_weight_kg' => 100]],
            'outputs' => [['output_type' => 'utama', 'output_weight_kg' => 72]],
            'wastes'  => [
                ['waste_category' => 'kepala', 'output_weight_kg' => 20],
                ['waste_category' => 'isi_perut', 'output_weight_kg' => 8],
            ],
        ]);

        $record = $hasil['record'];

        $this->assertSame('CUT-' . today()->format('ymd') . '-0001', $record->record_code);
        $this->assertEqualsWithDelta(100.0, (float) $record->total_input_kg, 0.001);
        $this->assertEqualsWithDelta(72.0,  (float) $record->total_output_kg, 0.001);
        $this->assertEqualsWithDelta(28.0,  (float) $record->total_waste_kg, 0.001);
        $this->assertEqualsWithDelta(72.0,  (float) $record->yield_pct, 0.01);
        $this->assertEqualsWithDelta(0.0,   $record->unaccounted_kg, 0.001);

        // Batch asal habis, lot anak terbit dengan nomor yang menempel pada induknya.
        $this->assertEqualsWithDelta(0.0, (float) $bahan->fresh()->current_weight_kg, 0.001);
        $this->assertSame('consumed', $bahan->fresh()->status);

        $lot = ProductionBatch::where('process_stage_id', $this->cutting->id)->firstOrFail();
        $this->assertSame($bahan->batch_number . '-CT01', $lot->batch_number);
        $this->assertEqualsWithDelta(72.0, (float) $lot->current_weight_kg, 0.001);
        $this->assertSame(today()->toDateString(), $lot->effective_date->toDateString());
    }

    public function test_rantai_tiga_tahap_menyimpan_tanggal_tiap_tahap(): void
    {
        $bahan = $this->terima(100, $this->njy)->first();

        $cut = app(StageService::class)->record($this->cutting, [
            'purchase_order_id' => $this->po->id,
            'process_date'      => today()->toDateString(),
            'inputs'  => [['batch_id' => $bahan->id, 'input_weight_kg' => 100]],
            'outputs' => [['output_type' => 'utama', 'output_weight_kg' => 72]],
            'wastes'  => [['waste_category' => 'kepala', 'output_weight_kg' => 28]],
        ])['batches']->first();

        $trim = app(StageService::class)->record($this->trimming, [
            'purchase_order_id' => $this->po->id,
            'process_date'      => today()->addDay()->toDateString(),
            'inputs'  => [['batch_id' => $cut->id, 'input_weight_kg' => 72]],
            'outputs' => [['output_type' => 'utama', 'product_type_id' => $this->steak->id, 'output_weight_kg' => 62]],
            'wastes'  => [['waste_category' => 'kulit', 'output_weight_kg' => 10]],
        ])['batches']->first();

        $steak = app(StageService::class)->record($this->steakStage, [
            'purchase_order_id' => $this->po->id,
            'process_date'      => today()->addDays(2)->toDateString(),
            'inputs'  => [['batch_id' => $trim->id, 'input_weight_kg' => 62]],
            'outputs' => [['output_type' => 'utama', 'product_type_id' => $this->steak->id, 'output_weight_kg' => 58]],
        ])['batches']->first();

        // Rantai nomor merekam seluruh jalur.
        $this->assertSame($bahan->batch_number . '-CT01-TR01-ST01', $steak->batch_number);

        // Tanggal tiap tahap tersimpan terpisah. Inilah sumber kolom tanggal
        // bongkar/potong/trimming/steak di kartu telusur.
        $this->assertSame(today()->toDateString(),            $bahan->effective_date->toDateString());
        $this->assertSame(today()->toDateString(),            $cut->fresh()->effective_date->toDateString());
        $this->assertSame(today()->addDay()->toDateString(),  $trim->fresh()->effective_date->toDateString());
        $this->assertSame(today()->addDays(2)->toDateString(), $steak->effective_date->toDateString());

        // Lot tahap akhir siap masuk packing.
        $this->assertSame('finished', $steak->content_type);
        $this->assertTrue($this->steakStage->is_final_stage);
    }

    public function test_gabungan_beberapa_batch_mencatat_kontribusi_tiap_asal(): void
    {
        $a = $this->terima(200, $this->njy)->first();
        $b = $this->terima(300, $this->ocf)->first();
        $c = $this->terima(500, $this->njy)->first();

        $hasil = app(StageService::class)->record($this->cutting, [
            'purchase_order_id' => $this->po->id,
            'process_date'      => today()->toDateString(),
            'inputs' => [
                ['batch_id' => $a->id, 'input_weight_kg' => 200],
                ['batch_id' => $b->id, 'input_weight_kg' => 300],
                ['batch_id' => $c->id, 'input_weight_kg' => 500],
            ],
            'outputs' => [['output_type' => 'utama', 'output_weight_kg' => 700]],
            'wastes'  => [['waste_category' => 'kepala', 'output_weight_kg' => 300]],
        ]);

        $lot = $hasil['batches']->first();

        $this->assertTrue($lot->is_multi_source);
        $this->assertStringStartsWith('MIX-', $lot->batch_number,
            'Lot gabungan tidak boleh memakai nomor salah satu induk saja.');

        $links = BatchStageLink::where('output_batch_id', $lot->id)->get();
        $this->assertCount(3, $links);

        // Alokasi proporsional: 20% / 30% / 50% dari 700 kg.
        $this->assertEqualsWithDelta(140.0, (float) $links->firstWhere('source_batch_id', $a->id)->contributed_kg, 0.01);
        $this->assertEqualsWithDelta(210.0, (float) $links->firstWhere('source_batch_id', $b->id)->contributed_kg, 0.01);
        $this->assertEqualsWithDelta(350.0, (float) $links->firstWhere('source_batch_id', $c->id)->contributed_kg, 0.01);

        // Jumlah kontribusi harus persis sama dengan berat lot hasil.
        $this->assertEqualsWithDelta(700.0, (float) $links->sum('contributed_kg'), 0.001);
    }

    public function test_satu_batch_bisa_dipakai_beberapa_sesi(): void
    {
        $bahan = $this->terima(800, $this->njy)->first();

        app(StageService::class)->record($this->cutting, [
            'purchase_order_id' => $this->po->id,
            'process_date'      => today()->toDateString(),
            'inputs'  => [['batch_id' => $bahan->id, 'input_weight_kg' => 300]],
            'outputs' => [['output_type' => 'utama', 'output_weight_kg' => 220]],
            'wastes'  => [['waste_category' => 'kepala', 'output_weight_kg' => 80]],
        ]);

        $this->assertSame('partial', $bahan->fresh()->status);
        $this->assertEqualsWithDelta(500.0, (float) $bahan->fresh()->current_weight_kg, 0.001);

        app(StageService::class)->record($this->cutting, [
            'purchase_order_id' => $this->po->id,
            'process_date'      => today()->toDateString(),
            'inputs'  => [['batch_id' => $bahan->id, 'input_weight_kg' => 500]],
            'outputs' => [['output_type' => 'utama', 'output_weight_kg' => 360]],
            'wastes'  => [['waste_category' => 'kepala', 'output_weight_kg' => 140]],
        ]);

        $this->assertSame('consumed', $bahan->fresh()->status);
        $this->assertEqualsWithDelta(0.0, (float) $bahan->fresh()->current_weight_kg, 0.001);

        // Dua lot terpisah dengan urutan berbeda di bawah induk yang sama.
        $lots = ProductionBatch::where('parent_batch_id', $bahan->id)->pluck('batch_number');
        $this->assertContains($bahan->batch_number . '-CT01', $lots);
        $this->assertContains($bahan->batch_number . '-CT02', $lots);
    }

    public function test_hasil_melebihi_bahan_masuk_ditolak(): void
    {
        $bahan = $this->terima(100, $this->njy)->first();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/melebihi bahan yang masuk/');

        app(StageService::class)->record($this->cutting, [
            'purchase_order_id' => $this->po->id,
            'process_date'      => today()->toDateString(),
            'inputs'  => [['batch_id' => $bahan->id, 'input_weight_kg' => 100]],
            'outputs' => [['output_type' => 'utama', 'output_weight_kg' => 90]],
            'wastes'  => [['waste_category' => 'kepala', 'output_weight_kg' => 30]],
        ]);
    }

    public function test_input_melebihi_sisa_batch_ditolak(): void
    {
        $bahan = $this->terima(100, $this->njy)->first();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/tidak cukup/');

        app(StageService::class)->record($this->cutting, [
            'purchase_order_id' => $this->po->id,
            'process_date'      => today()->toDateString(),
            'inputs'  => [['batch_id' => $bahan->id, 'input_weight_kg' => 150]],
            'outputs' => [['output_type' => 'utama', 'output_weight_kg' => 100]],
        ]);
    }

    public function test_yield_di_bawah_standar_memunculkan_alert(): void
    {
        YieldStandard::create([
            'material_subgroup_id' => $this->subgroup->id,
            'process_stage_id'     => $this->trimming->id,
            'scope'                => 'stage',
            'min_yield_pct'        => 85,
            'warn_yield_pct'       => 88,
            'effective_from'       => today()->subMonth(),
            'is_active'            => true,
        ]);

        $bahan = $this->terima(100, $this->njy)->first();
        $cut   = app(StageService::class)->record($this->cutting, [
            'purchase_order_id' => $this->po->id,
            'process_date'      => today()->toDateString(),
            'inputs'  => [['batch_id' => $bahan->id, 'input_weight_kg' => 100]],
            'outputs' => [['output_type' => 'utama', 'output_weight_kg' => 72]],
            'wastes'  => [['waste_category' => 'kepala', 'output_weight_kg' => 28]],
        ])['batches']->first();

        // 60 / 72 = 83,3% -> di bawah standar 85%
        $hasil = app(StageService::class)->record($this->trimming, [
            'purchase_order_id' => $this->po->id,
            'process_date'      => today()->toDateString(),
            'inputs'  => [['batch_id' => $cut->id, 'input_weight_kg' => 72]],
            'outputs' => [['output_type' => 'utama', 'product_type_id' => $this->steak->id, 'output_weight_kg' => 60]],
            'wastes'  => [['waste_category' => 'kulit', 'output_weight_kg' => 12]],
        ]);

        $this->assertCount(1, $hasil['alerts']);
        $this->assertSame('below', $hasil['alerts'][0]['level']);
        $this->assertStringContainsString('melebihi toleransi pabrik', $hasil['alerts'][0]['message']);
    }

    public function test_standar_lama_tetap_dipakai_untuk_tanggal_lama(): void
    {
        // Versi lama: minimal 70%, ditutup akhir bulan lalu.
        YieldStandard::create([
            'material_subgroup_id' => $this->subgroup->id,
            'process_stage_id'     => $this->cutting->id,
            'scope'                => 'stage',
            'min_yield_pct'        => 70,
            'effective_from'       => today()->subMonths(6),
            'effective_to'         => today()->subDays(10),
            'is_active'            => true,
        ]);
        // Versi baru: minimal 80%.
        YieldStandard::create([
            'material_subgroup_id' => $this->subgroup->id,
            'process_stage_id'     => $this->cutting->id,
            'scope'                => 'stage',
            'min_yield_pct'        => 80,
            'effective_from'       => today()->subDays(9),
            'is_active'            => true,
        ]);

        $lama = YieldStandard::resolve($this->subgroup->id, $this->cutting->id, today()->subDays(20));
        $baru = YieldStandard::resolve($this->subgroup->id, $this->cutting->id, today());

        $this->assertEqualsWithDelta(70.0, (float) $lama->min_yield_pct, 0.01,
            'Produksi lama harus dinilai dengan standar yang berlaku saat itu.');
        $this->assertEqualsWithDelta(80.0, (float) $baru->min_yield_pct, 0.01);
    }

    public function test_pembatalan_sesi_mengembalikan_bahan_tanpa_menghapus_riwayat(): void
    {
        $bahan = $this->terima(100, $this->njy)->first();

        $hasil  = app(StageService::class)->record($this->cutting, [
            'purchase_order_id' => $this->po->id,
            'process_date'      => today()->toDateString(),
            'inputs'  => [['batch_id' => $bahan->id, 'input_weight_kg' => 100]],
            'outputs' => [['output_type' => 'utama', 'output_weight_kg' => 72]],
            'wastes'  => [['waste_category' => 'kepala', 'output_weight_kg' => 28]],
        ]);
        $record = $hasil['record'];
        $lot    = $hasil['batches']->first();

        app(StageService::class)->void($record, 'Salah timbang');

        $this->assertSame('voided', $record->fresh()->status);
        $this->assertSame('voided', $lot->fresh()->status);
        $this->assertEqualsWithDelta(100.0, (float) $bahan->fresh()->current_weight_kg, 0.001,
            'Bahan harus kembali utuh ke batch asal.');

        // Catatan sesi tetap ada, hanya berstatus dibatalkan.
        $this->assertDatabaseHas('batch_stage_records', ['id' => $record->id, 'void_reason' => 'Salah timbang']);
        $this->assertDatabaseHas('batch_movements', ['batch_id' => $bahan->id, 'movement_type' => 'reversal']);
    }

    public function test_hasil_yang_sudah_diproses_lanjut_tidak_bisa_dibatalkan(): void
    {
        $bahan = $this->terima(100, $this->njy)->first();

        $cutHasil = app(StageService::class)->record($this->cutting, [
            'purchase_order_id' => $this->po->id,
            'process_date'      => today()->toDateString(),
            'inputs'  => [['batch_id' => $bahan->id, 'input_weight_kg' => 100]],
            'outputs' => [['output_type' => 'utama', 'output_weight_kg' => 72]],
            'wastes'  => [['waste_category' => 'kepala', 'output_weight_kg' => 28]],
        ]);

        app(StageService::class)->record($this->trimming, [
            'purchase_order_id' => $this->po->id,
            'process_date'      => today()->toDateString(),
            'inputs'  => [['batch_id' => $cutHasil['batches']->first()->id, 'input_weight_kg' => 72]],
            'outputs' => [['output_type' => 'utama', 'product_type_id' => $this->steak->id, 'output_weight_kg' => 62]],
            'wastes'  => [['waste_category' => 'kulit', 'output_weight_kg' => 10]],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/sudah diproses lebih lanjut/');

        app(StageService::class)->void($cutHasil['record'], 'Coba batalkan');
    }
}
