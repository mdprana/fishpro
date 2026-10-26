<?php

namespace Tests\Feature;

use App\Models\ProcessStage;
use App\Models\ProductionBatch;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Setiap halaman harus merender, baik saat basis data masih kosong maupun
 * setelah terisi data contoh. Banyak kerusakan view hanya muncul di salah satu
 * dari dua keadaan itu.
 */
class AllPagesSmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        $this->user = User::firstOrFail();
        $this->actingAs($this->user);
    }

    public static function halamanStatis(): array
    {
        return [
            'dashboard'        => ['dashboard'],
            'daftar PO'        => ['po.index'],
            'buat PO'          => ['po.create'],
            'bahan baku'       => ['receiving.index'],
            'terima bahan'     => ['receiving.create'],
            'batch'            => ['batch.index'],
            'telusur'          => ['trace.index'],
            'produksi'         => ['production.index'],
            'analisa'          => ['production.analisa'],
            'packing'          => ['packing.index'],
            'input packing'    => ['packing.create'],
            'daftar box'       => ['packing.boxes'],
            'riwayat'          => ['riwayat.index'],
            'yield & recovery' => ['report.yield'],
            'rekap bahan baku' => ['report.raw-material'],
            'group bahan'      => ['master.material-groups'],
            'group produk'     => ['master.product-groups'],
            'supplier'         => ['master.suppliers'],
            'standar yield'    => ['master.yield-standards'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('halamanStatis')]
    public function test_halaman_tampil(string $routeName): void
    {
        $this->get(route($routeName))->assertOk();
    }

    public function test_halaman_tiap_tahap_tampil(): void
    {
        foreach (ProcessStage::all() as $stage) {
            $this->get(route('stage.index', $stage->code))->assertOk();
            $this->get(route('stage.create', $stage->code))->assertOk();
        }
    }

    public function test_halaman_detail_po_tampil(): void
    {
        foreach (PurchaseOrder::all() as $po) {
            $this->get(route('po.show', $po))->assertOk();
            $this->get(route('po.edit', $po))->assertOk();
            $this->get(route('production.analisa', ['po_id' => $po->id]))->assertOk();
            $this->get(route('production.analisa.pdf', $po))->assertOk();
            $this->get(route('production.analisa.export', $po))->assertOk();
        }
    }

    public function test_halaman_batch_dan_label_tampil(): void
    {
        $batches = ProductionBatch::limit(5)->get();
        $this->assertNotEmpty($batches, 'Seeder harus menghasilkan batch.');

        foreach ($batches as $batch) {
            $this->get(route('batch.show', $batch))->assertOk();
            $this->get(route('batch.label', $batch))->assertOk();
            $this->get(route('trace.batch', $batch->qr_code))->assertOk();
        }

        $this->get(route('batch.labels', ['ids' => $batches->pluck('id')->implode(',')]))->assertOk();
    }

    public function test_endpoint_api_tampil(): void
    {
        $this->getJson(route('api.batch.available'))->assertOk();
        $this->getJson(route('api.packing.lots'))->assertOk();
        $this->getJson(route('api.supplier.lookup'))->assertOk();
        $this->getJson(route('api.master.products-grouped'))->assertOk();

        foreach (ProcessStage::all() as $stage) {
            $this->getJson(route('api.stage.inputs', $stage->code))->assertOk();
        }
    }
}
