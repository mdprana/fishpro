<?php

namespace Tests\Feature;

use App\Models\BatchMovement;
use App\Models\MaterialGroup;
use App\Models\MaterialSubgroup;
use App\Models\ProductionBatch;
use App\Models\PurchaseOrder;
use App\Models\RawMaterialStock;
use App\Models\Receiving;
use App\Models\Supplier;
use App\Models\User;
use App\Services\BatchService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchLedgerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Supplier $supplier;
    private MaterialSubgroup $subgroup;
    private PurchaseOrder $po;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Operator', 'email' => 'op@fishpro.test', 'password' => bcrypt('secret'),
        ]);
        $this->actingAs($this->user);

        $this->supplier = Supplier::create(['code' => 'NJY', 'name' => 'UD Nelayan Jaya']);

        $group = MaterialGroup::create(['name' => 'YF LOIN', 'unit' => 'kg', 'color' => '#2563eb']);
        $this->subgroup = MaterialSubgroup::create([
            'material_group_id' => $group->id, 'name' => 'YF LOIN EROPA WARNA', 'code' => 'YFW',
        ]);

        $this->po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-001', 'buyer_name' => 'Buyer',
            'order_date' => today(), 'deadline' => today()->addDays(7),
            'status' => 'active', 'created_by' => $this->user->id,
        ]);
    }

    private function terima(float $kg, int $wadah, bool $split): Receiving
    {
        return Receiving::create([
            'material_subgroup_id' => $this->subgroup->id,
            'purchase_order_id'    => $this->po->id,
            'supplier_id'          => $this->supplier->id,
            'weight_kg'            => $kg,
            'container_type'       => 'Keranjang',
            'container_count'      => $wadah,
            'split_per_container'  => $split,
            'received_date'        => today(),
            'created_by'           => $this->user->id,
        ]);
    }

    public function test_penerimaan_menerbitkan_batch_induk_dengan_qr(): void
    {
        $batches = app(BatchService::class)->createFromReceiving($this->terima(200, 1, false));

        $this->assertCount(1, $batches);

        $parent = $batches->first();
        $this->assertSame('RM-' . today()->format('ymd') . '-NJY-YFW-001', $parent->batch_number);
        $this->assertSame(0, $parent->level);
        $this->assertSame($parent->id, $parent->root_batch_id);
        $this->assertSame(200.0, (float) $parent->current_weight_kg);
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{8}$/', $parent->qr_code);

        // Satu baris ledger penerimaan.
        $this->assertDatabaseCount('batch_movements', 1);
        $this->assertDatabaseHas('batch_movements', [
            'batch_id' => $parent->id, 'movement_type' => 'receipt', 'direction' => 'in',
        ]);
    }

    public function test_pecah_per_wadah_membuat_satu_lot_per_keranjang_tanpa_kehilangan_berat(): void
    {
        $batches = app(BatchService::class)->createFromReceiving($this->terima(800, 20, true));

        $this->assertCount(20, $batches);
        // Total anak harus persis sama dengan berat kiriman, termasuk sisa pembulatan.
        $this->assertEqualsWithDelta(800.0, $batches->sum(fn ($b) => (float) $b->current_weight_kg), 0.001);

        $parent = ProductionBatch::roots()->first();
        $this->assertEqualsWithDelta(0.0, (float) $parent->fresh()->current_weight_kg, 0.001,
            'Isi induk sudah dipindahkan seluruhnya ke wadah-wadahnya.');

        $this->assertSame(
            'RM-' . today()->format('ymd') . '-NJY-YFW-001/P03',
            $batches->get(2)->batch_number
        );
    }

    public function test_stok_bahan_baku_mengikuti_saldo_batch(): void
    {
        app(BatchService::class)->createFromReceiving($this->terima(800, 20, true));
        app(BatchService::class)->createFromReceiving($this->terima(200, 1, false));

        $stok = RawMaterialStock::where('material_subgroup_id', $this->subgroup->id)->value('current_stock_kg');
        $this->assertEqualsWithDelta(1000.0, (float) $stok, 0.01);

        // Pakai 300 kg dari batch utuh -> stok ikut turun setelah dihitung ulang.
        $batch = ProductionBatch::where('current_weight_kg', 200)->firstOrFail();
        $batch->applyMovement(BatchMovement::TYPE_STAGE_CONSUME, 'out', 150);
        app(StockService::class)->recomputeSubgroup($this->subgroup->id);

        $stok = RawMaterialStock::where('material_subgroup_id', $this->subgroup->id)->value('current_stock_kg');
        $this->assertEqualsWithDelta(850.0, (float) $stok, 0.01);
    }

    public function test_batch_tidak_bisa_diambil_melebihi_sisanya(): void
    {
        $batch = app(BatchService::class)->createFromReceiving($this->terima(100, 1, false))->first();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/hanya tersisa/');

        $batch->applyMovement(BatchMovement::TYPE_STAGE_CONSUME, 'out', 150);
    }

    public function test_status_batch_mengikuti_sisa_isinya(): void
    {
        $batch = app(BatchService::class)->createFromReceiving($this->terima(100, 1, false))->first();
        $this->assertSame('available', $batch->status);

        $batch->applyMovement(BatchMovement::TYPE_STAGE_CONSUME, 'out', 40);
        $this->assertSame('partial', $batch->fresh()->status);

        $batch->applyMovement(BatchMovement::TYPE_STAGE_CONSUME, 'out', 60);
        $this->assertSame('consumed', $batch->fresh()->status);
    }

    public function test_baris_ledger_tidak_bisa_diubah_atau_dihapus(): void
    {
        $batch    = app(BatchService::class)->createFromReceiving($this->terima(100, 1, false))->first();
        $movement = $batch->movements()->first();

        try {
            $movement->update(['weight_kg' => 999]);
            $this->fail('Ledger seharusnya menolak perubahan.');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }

        try {
            $movement->delete();
            $this->fail('Ledger seharusnya menolak penghapusan.');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }

        $this->assertEqualsWithDelta(100.0, (float) $movement->fresh()->weight_kg, 0.001);
    }

    public function test_saldo_cache_selalu_sama_dengan_hasil_hitung_ulang_ledger(): void
    {
        $batch = app(BatchService::class)->createFromReceiving($this->terima(500, 1, false))->first();

        $batch->applyMovement(BatchMovement::TYPE_STAGE_CONSUME, 'out', 120);
        $batch->applyMovement(BatchMovement::TYPE_ADJUSTMENT, 'in', 5.5);
        $batch->applyMovement(BatchMovement::TYPE_WASTE, 'out', 30.25);

        $cache  = (float) $batch->fresh()->current_weight_kg;
        $ledger = $batch->fresh()->recalcFromLedger();

        $this->assertEqualsWithDelta($cache, $ledger, 0.001);
        $this->assertEqualsWithDelta(355.25, $ledger, 0.001);
        $this->assertCount(0, app(StockService::class)->findLedgerMismatches());
    }

    public function test_penerimaan_yang_sudah_dipakai_tidak_bisa_dibatalkan(): void
    {
        $receiving = $this->terima(100, 1, false);
        $batch     = app(BatchService::class)->createFromReceiving($receiving)->first();

        $batch->applyMovement(BatchMovement::TYPE_STAGE_CONSUME, 'out', 25);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/sudah dipakai produksi/');

        app(BatchService::class)->voidReceivingBatches($receiving);
    }

    public function test_pembatalan_penerimaan_menulis_reversal_bukan_menghapus_riwayat(): void
    {
        $receiving = $this->terima(100, 1, false);
        $batch     = app(BatchService::class)->createFromReceiving($receiving)->first();

        app(BatchService::class)->voidReceivingBatches($receiving, 'Barang ditolak QC');

        $batch->refresh();
        $this->assertSame('voided', $batch->status);
        $this->assertEqualsWithDelta(0.0, (float) $batch->current_weight_kg, 0.001);

        // Baris penerimaan asli harus tetap ada di samping baris pembatalannya.
        $this->assertDatabaseHas('batch_movements', ['batch_id' => $batch->id, 'movement_type' => 'receipt']);
        $this->assertDatabaseHas('batch_movements', ['batch_id' => $batch->id, 'movement_type' => 'reversal']);

        $stok = RawMaterialStock::where('material_subgroup_id', $this->subgroup->id)->value('current_stock_kg');
        $this->assertEqualsWithDelta(0.0, (float) $stok, 0.01);
    }
}
