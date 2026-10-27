<?php

namespace Tests\Feature;

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
use App\Services\BatchService;
use App\Services\PackingService;
use App\Services\StageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rantai penuh: Bongkar -> Potong -> Trimming -> Steak -> Packing,
 * lalu dibaca balik dari QR sebuah box.
 */
class TraceabilityTest extends TestCase
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
            'po_number' => 'PO-2026-010', 'buyer_name' => 'PT Seafood Nusantara',
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

    /** Jalankan rantai penuh sampai lot siap packing. */
    private function rantaiPenuh(array $bahan): ProductionBatch
    {
        $cut = app(StageService::class)->record($this->cutting, [
            'purchase_order_id' => $this->po->id,
            'process_date'      => today()->subDays(4)->toDateString(),
            'operator_name'     => 'Tim Potong',
            'inputs'  => collect($bahan)->map(fn ($b) => [
                'batch_id' => $b->id, 'input_weight_kg' => (float) $b->current_weight_kg,
            ])->all(),
            'outputs' => [['output_type' => 'utama', 'output_weight_kg' => 720]],
            'wastes'  => [['waste_category' => 'kepala', 'output_weight_kg' => 280]],
        ])['batches']->first();

        $trim = app(StageService::class)->record($this->trimming, [
            'purchase_order_id' => $this->po->id,
            'process_date'      => today()->subDays(3)->toDateString(),
            'operator_name'     => 'Tim Trimming',
            'inputs'  => [['batch_id' => $cut->id, 'input_weight_kg' => 720]],
            'outputs' => [['output_type' => 'utama', 'product_type_id' => $this->steak->id, 'output_weight_kg' => 620]],
            'wastes'  => [['waste_category' => 'kulit', 'output_weight_kg' => 100]],
        ])['batches']->first();

        return app(StageService::class)->record($this->steakStage, [
            'purchase_order_id' => $this->po->id,
            'process_date'      => today()->subDays(2)->toDateString(),
            'operator_name'     => 'Tim Steak',
            'inputs'  => [['batch_id' => $trim->id, 'input_weight_kg' => 620]],
            'outputs' => [['output_type' => 'utama', 'product_type_id' => $this->steak->id, 'output_weight_kg' => 600]],
        ])['batches']->first();
    }

    public function test_dari_qr_box_semua_tanggal_tahap_dan_supplier_terbaca(): void
    {
        $a = $this->terima(600, $this->njy, 8);
        $b = $this->terima(400, $this->ocf, 7);

        $lot = $this->rantaiPenuh([$a, $b]);

        $hasil = app(PackingService::class)->bulkGenerate(
            $this->po, $this->steak,
            [['batch_id' => $lot->id, 'kg' => 500]],
            100, 5,
            ['packing_date' => today()->toDateString(), 'operator_name' => 'Tim Packing']
        );

        $this->assertCount(100, $hasil['boxes']);

        $box   = $hasil['boxes']->first();
        $trace = $box->traceSources();

        // Box berisi campuran dua supplier karena sesi potong menggabungkannya.
        $this->assertTrue($trace['is_mixed']);
        $this->assertSame(2, $trace['batch_count']);
        $this->assertSame(2, $trace['supplier_count']);

        $kode = collect($trace['sources'])->pluck('supplier_code')->sort()->values()->all();
        $this->assertSame(['NJY', 'OCF'], $kode);

        // Tujuh kolom yang diminta harus terisi semua.
        foreach ($trace['sources'] as $src) {
            $this->assertNotNull($src['root_batch_number'], 'batch');
            $this->assertNotNull($src['supplier_code'],     'kode supplier');
            $this->assertNotNull($src['tgl_bongkar'],       'tgl bongkar');
            $this->assertNotNull($src['tgl_potong'],        'tgl potong');
            $this->assertNotNull($src['tgl_trimming'],      'tgl trimming');
            $this->assertNotNull($src['tgl_steak'],         'tgl potong steak');
            $this->assertNotNull($src['tgl_packing'],       'tgl packing');
        }

        $njy = collect($trace['sources'])->firstWhere('supplier_code', 'NJY');
        $this->assertSame(today()->subDays(8)->toDateString(), $njy['tgl_bongkar']->toDateString());
        $this->assertSame(today()->subDays(4)->toDateString(), $njy['tgl_potong']->toDateString());
        $this->assertSame(today()->subDays(3)->toDateString(), $njy['tgl_trimming']->toDateString());
        $this->assertSame(today()->subDays(2)->toDateString(), $njy['tgl_steak']->toDateString());
        $this->assertSame(today()->toDateString(),             $njy['tgl_packing']->toDateString());

        // Supplier OCF punya tanggal bongkar sendiri.
        $ocf = collect($trace['sources'])->firstWhere('supplier_code', 'OCF');
        $this->assertSame(today()->subDays(7)->toDateString(), $ocf['tgl_bongkar']->toDateString());

        // Kontribusi tiap asal berjumlah persis berat netto box.
        $this->assertEqualsWithDelta(5.0, collect($trace['sources'])->sum('contributed_kg'), 0.01);
        // 600 : 400 -> 60% : 40%
        $this->assertEqualsWithDelta(3.0, $njy['contributed_kg'], 0.05);
        $this->assertEqualsWithDelta(2.0, $ocf['contributed_kg'], 0.05);
    }

    public function test_lima_ratus_box_bisa_berbeda_batch_dan_kiloan(): void
    {
        $a = $this->terima(600, $this->njy, 8);
        $b = $this->terima(400, $this->ocf, 7);
        $lot = $this->rantaiPenuh([$a, $b]);

        $hasil = app(PackingService::class)->bulkGenerate(
            $this->po, $this->steak,
            [['batch_id' => $lot->id, 'kg' => 500]],
            100, 5,
            ['packing_date' => today()->toDateString()]
        );

        $boxes = $hasil['boxes'];

        // Tiap box punya kode dan QR sendiri, tidak ada yang kembar.
        $this->assertCount(100, $boxes->pluck('box_code')->unique());
        $this->assertCount(100, $boxes->pluck('qr_code')->unique());
        $this->assertSame('BOX-PO2026010-YFSTK-0001', $boxes->first()->box_code);
        $this->assertSame('BOX-PO2026010-YFSTK-0100', $boxes->last()->box_code);

        // Berat satu box dikoreksi: hanya box itu yang berubah.
        $box = $boxes->get(6);
        app(PackingService::class)->adjustBoxWeight($box, 4.8);

        $this->assertEqualsWithDelta(4.8, (float) $box->fresh()->net_weight_kg, 0.001);
        $this->assertEqualsWithDelta(5.0, (float) $boxes->get(5)->fresh()->net_weight_kg, 0.001);

        // Selisihnya tercatat sebagai koreksi di ledger, bukan menimpa diam-diam.
        $this->assertDatabaseHas('batch_movements', [
            'packing_box_id' => $box->id,
            'movement_type'  => 'adjustment',
            'direction'      => 'in',   // box mengecil -> isi kembali ke lot
        ]);

        // Komposisi tiap box tetap berjumlah sama dengan beratnya.
        foreach ([$boxes->first(), $box->fresh(), $boxes->last()] as $b) {
            $this->assertEqualsWithDelta(
                (float) $b->net_weight_kg,
                (float) $b->sources()->sum('contributed_kg'),
                0.01
            );
        }
    }

    public function test_packing_melebihi_lot_yang_tersedia_ditolak(): void
    {
        $a   = $this->terima(600, $this->njy, 8);
        $b   = $this->terima(400, $this->ocf, 7);
        $lot = $this->rantaiPenuh([$a, $b]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/hanya menyediakan|tidak cukup/');

        app(PackingService::class)->bulkGenerate(
            $this->po, $this->steak,
            [['batch_id' => $lot->id, 'kg' => 700]],   // lot hanya berisi 600 kg
            140, 5,
            ['packing_date' => today()->toDateString()]
        );
    }

    public function test_membatalkan_box_mengembalikan_isinya_ke_lot(): void
    {
        $a   = $this->terima(600, $this->njy, 8);
        $b   = $this->terima(400, $this->ocf, 7);
        $lot = $this->rantaiPenuh([$a, $b]);

        $box = app(PackingService::class)->bulkGenerate(
            $this->po, $this->steak,
            [['batch_id' => $lot->id, 'kg' => 50]],
            10, 5,
            ['packing_date' => today()->toDateString()]
        )['boxes']->first();

        $sisaSebelum = (float) $lot->fresh()->current_weight_kg;

        app(PackingService::class)->voidBox($box, 'Kemasan sobek');

        $this->assertSame('voided', $box->fresh()->status);
        $this->assertEqualsWithDelta($sisaSebelum + 5, (float) $lot->fresh()->current_weight_kg, 0.01);
        // Kode box tidak dipakai ulang, barisnya tetap ada.
        $this->assertDatabaseHas('packing_boxes', ['box_code' => $box->box_code, 'status' => 'voided']);
    }

    public function test_halaman_telusur_box_menampilkan_tujuh_kolom(): void
    {
        $a   = $this->terima(600, $this->njy, 8);
        $b   = $this->terima(400, $this->ocf, 7);
        $lot = $this->rantaiPenuh([$a, $b]);

        $box = app(PackingService::class)->bulkGenerate(
            $this->po, $this->steak,
            [['batch_id' => $lot->id, 'kg' => 25]],
            5, 5,
            ['packing_date' => today()->toDateString()]
        )['boxes']->first();

        $this->get(route('trace.box', $box->qr_code))
            ->assertOk()
            ->assertSee($box->box_code)
            ->assertSee('Tgl Bongkar')
            ->assertSee('Tgl Potong')
            ->assertSee('Tgl Trimming')
            ->assertSee('Tgl Potong Steak')
            ->assertSee('Tgl Packing')
            ->assertSee('NJY')
            ->assertSee('OCF')
            ->assertSee('Box campuran');

        // Scan kode box juga harus mengarah ke kartu telusur yang sama.
        $this->post(route('trace.resolve'), ['code' => $box->box_code])
            ->assertRedirect(route('trace.box', $box->qr_code));
    }

    public function test_recovery_po_dihitung_dari_packing_dibagi_bahan_baku(): void
    {
        $a   = $this->terima(600, $this->njy, 8);
        $b   = $this->terima(400, $this->ocf, 7);
        $lot = $this->rantaiPenuh([$a, $b]);

        app(PackingService::class)->bulkGenerate(
            $this->po, $this->steak,
            [['batch_id' => $lot->id, 'kg' => 500]],
            100, 5,
            ['packing_date' => today()->toDateString()]
        );

        $po = $this->po->fresh();

        $this->assertEqualsWithDelta(1000.0, $po->total_received_kg, 0.01);
        $this->assertEqualsWithDelta(500.0,  $po->total_packed_kg, 0.01);
        $this->assertSame(100, $po->total_packed_box);
        // 500 / 1000 x 100
        $this->assertEqualsWithDelta(50.0, $po->recovery_pct, 0.01);
    }

    /**
     * Regresi: kolom denormalisasi pernah hanya menyimpan SATU batch induk per
     * lot, sehingga rekap agregat melaporkan seluruh box berasal dari satu
     * supplier saja padahal isinya campuran.
     */
    public function test_rekap_agregat_menyebut_semua_supplier_bukan_hanya_yang_dominan(): void
    {
        $a = $this->terima(600, $this->njy, 8);
        $b = $this->terima(400, $this->ocf, 7);

        $lot = $this->rantaiPenuh([$a, $b]);

        app(PackingService::class)->bulkGenerate(
            $this->po, $this->steak,
            [['batch_id' => $lot->id, 'kg' => 500]],
            100, 5,
            ['packing_date' => today()->toDateString()]
        );

        // Satu query GROUP BY harus sudah cukup untuk rekap "dari mana saja".
        $perSupplier = \Illuminate\Support\Facades\DB::table('packing_box_sources as s')
            ->join('suppliers as sup', 'sup.id', '=', 's.supplier_id')
            ->groupBy('sup.code')
            ->selectRaw('sup.code, SUM(s.contributed_kg) as kg')
            ->get()
            ->pluck('kg', 'code');

        $this->assertCount(2, $perSupplier, 'Kedua supplier harus muncul di rekap.');
        // 600 : 400 dari 500 kg yang dipacking.
        $this->assertEqualsWithDelta(300.0, (float) $perSupplier['NJY'], 1.0);
        $this->assertEqualsWithDelta(200.0, (float) $perSupplier['OCF'], 1.0);
        $this->assertEqualsWithDelta(500.0, (float) $perSupplier->sum(), 0.1);

        // Tiap box juga ditandai campuran, bukan hanya box tertentu.
        $this->assertSame(100, PackingBox::where('is_mixed_batch', true)->count());
    }

    public function test_telusur_maju_batch_induk_sampai_ke_box(): void
    {
        $a   = $this->terima(600, $this->njy, 8);
        $b   = $this->terima(400, $this->ocf, 7);
        $lot = $this->rantaiPenuh([$a, $b]);

        app(PackingService::class)->bulkGenerate(
            $this->po, $this->steak,
            [['batch_id' => $lot->id, 'kg' => 50]],
            10, 5,
            ['packing_date' => today()->toDateString()]
        );

        // Dari lot akhir, semua box yang memuatnya bisa ditemukan.
        $this->assertSame(10, PackingBox::whereHas('sources', fn ($q) => $q->where('batch_id', $lot->id))->count());

        // Dan dari batch induk NJY, box-nya juga terlacak lewat kolom denormalisasi.
        $this->assertSame(10, PackingBox::whereHas('sources', fn ($q) => $q->where('root_batch_id', $a->id))->count());
    }

    private function batchDanBox(): array
    {
        $a   = $this->terima(600, $this->njy, 8);
        $b   = $this->terima(400, $this->ocf, 7);
        $lot = $this->rantaiPenuh([$a, $b]);

        $box = app(PackingService::class)->bulkGenerate(
            $this->po, $this->steak,
            [['batch_id' => $lot->id, 'kg' => 10]],
            2, 5,
            ['packing_date' => today()->toDateString()]
        )['boxes']->first();

        return [$a, $box];
    }

    public function test_qr_resolver_menerima_token_nomor_batch_bergaris_miring_kode_box_dan_url_sendiri(): void
    {
        [$batch, $box] = $this->batchDanBox();
        $batch->update(['batch_number' => 'RM-1/P03']);
        $origin = rtrim(config('app.url'), '/');

        $kasus = [
            $batch->qr_code                          => route('trace.batch', $batch->qr_code),
            'RM-1/P03'                               => route('trace.batch', $batch->qr_code),
            "  RM-1/P03\t"                           => route('trace.batch', $batch->qr_code),
            $box->box_code                           => route('trace.box', $box->qr_code),
            $box->qr_code                            => route('trace.box', $box->qr_code),
            "{$origin}/trace/batch/{$batch->qr_code}" => route('trace.batch', $batch->qr_code),
            "{$origin}/trace/box/{$box->qr_code}"     => route('trace.box', $box->qr_code),
            $batch->qr_url                           => route('trace.batch', $batch->qr_code),
            $box->qr_url                             => route('trace.box', $box->qr_code),
        ];

        foreach ($kasus as $code => $tujuan) {
            $this->from(route('trace.index'))->post(route('trace.resolve'), ['code' => $code])
                ->assertRedirect($tujuan);
        }

        $this->postJson(route('trace.resolve'), ['code' => 'RM-1/P03'])
            ->assertOk()->assertJson(['type' => 'batch', 'qr_code' => $batch->qr_code]);
    }

    public function test_qr_resolver_menolak_url_asing_dan_input_berbahaya_tanpa_menulis_data(): void
    {
        [$batch] = $this->batchDanBox();
        $t      = $batch->qr_code;
        $origin = rtrim(config('app.url'), '/');
        $host   = parse_url($origin, PHP_URL_HOST);
        $scheme = parse_url($origin, PHP_URL_SCHEME);
        $port   = parse_url($origin, PHP_URL_PORT);
        $tables = ['production_batches', 'packing_boxes', 'batch_movements', 'audit_events', 'document_counters'];
        $before = collect($tables)->mapWithKeys(fn ($table) => [$table => \DB::table($table)->get()->toJson()])->all();

        $tolak = [
            "https://evil.example/trace/batch/{$t}",
            "http://evil.example/trace/batch/{$t}",
            "//{$host}/trace/batch/{$t}",
            "//evil.example/trace/batch/{$t}",
            "{$scheme}://user:pass@{$host}".($port ? ":{$port}" : '')."/trace/batch/{$t}",
            "{$scheme}://{$host}:1/trace/batch/{$t}",
            "{$origin}.evil.example/trace/batch/{$t}",
            "{$origin}/x/trace/batch/{$t}",
            "{$origin}/trace/batches/{$t}",
            "{$origin}/trace/batch/{$t}?a=1",
            "{$origin}/trace/batch/{$t}?",
            "{$origin}/trace/batch/{$t}#",
            "{$origin}/trace/box/{$t}",
            "{$origin}/trace/batch/{$t}#x",
            "{$origin}/trace/batch/{$t}/",
            "{$origin}/trace/batch%2F{$t}",
            "{$origin}/trace%2Fbatch/{$t}",
            "{$origin}/trace/batch/%{$t}",
            "{$origin}/trace/batch/%E0%A4%A",
            "{$origin}/trace/batch/%".bin2hex($t[0]).substr($t, 1),
            "{$origin}/trace/batch/{$t}%2f",
            "{$origin}/trace/batch/{$t}%5c",
            "{$origin}\\trace\\batch\\{$t}",
            "javascript:alert(1)//{$t}",
            "JaVaScRiPt:location='/trace/batch/{$t}'",
            "data:text/html,{$t}",
            "ftp://{$host}/trace/batch/{$t}",
            str_repeat('A', 513),
            "RM\n{$t}",
            "RM\x001",
            "\xff\xfe",
        ];

        foreach ($tolak as $code) {
            $res = $this->from(route('trace.index'))->post(route('trace.resolve'), ['code' => $code]);
            $res->assertRedirect(route('trace.index'));
            $res->assertSessionHasErrors('code');
            $this->postJson(route('trace.resolve'), ['code' => $code])->assertStatus(422);
        }

        foreach ([['code' => [$t]], ['code' => array_fill(0, 513, $t)], ['code' => ['a' => $t]], ['code' => 123], []] as $body) {
            $this->from(route('trace.index'))->post(route('trace.resolve'), $body)
                ->assertRedirect(route('trace.index'))->assertSessionHasErrors('code');
        }

        $this->assertSame($before, collect($tables)->mapWithKeys(fn ($table) => [$table => \DB::table($table)->get()->toJson()])->all());
    }

    public function test_qr_origin_tidak_mengikuti_host_atau_forwarded_host(): void
    {
        $batch = $this->terima(10, $this->njy, 1);
        config(['app.url' => 'https://fishpro.example']);
        $this->withHeaders(['Host' => 'evil.example', 'X-Forwarded-Host' => 'evil.example'])
            ->postJson('/trace/resolve', ['code' => "https://evil.example/trace/batch/{$batch->qr_code}"])
            ->assertStatus(422);
        $this->postJson('/trace/resolve', ['code' => "https://fishpro.example/trace/batch/{$batch->qr_code}"])
            ->assertOk()->assertJson(['url' => "https://fishpro.example/trace/batch/{$batch->qr_code}"]);
    }

    public function test_qr_kode_tidak_dikenal_ditampilkan_ter_escape(): void
    {
        $this->from(route('trace.index'))
            ->post(route('trace.resolve'), ['code' => '<script>alert(1)</script>'])
            ->assertRedirect(route('trace.index'));

        $this->get(route('trace.index'))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertSee('role="status"', false)
            ->assertSee('for="manualCode"', false);
    }

    public function test_scan_tahap_nomor_batch_bergaris_miring_tetap_persis(): void
    {
        $a = $this->terima(600, $this->njy, 8);
        $b = $this->terima(400, $this->ocf, 7);
        $a->update(['batch_number' => 'RM-1/P03']);
        $b->update(['batch_number' => 'RM-1/P031']);

        $this->get(route('stage.create', $this->cutting->code))
            ->assertOk()->assertSee('manualCode', false)->assertSee('const RESOLVE_URL', false);

        $token = $this->postJson(route('trace.resolve'), ['code' => 'RM-1/P03'])
            ->assertOk()->assertJson(['type' => 'batch'])->json('qr_code');
        $this->assertSame($a->qr_code, $token);
        $this->getJson(route('api.batch.lookup', $token))
            ->assertOk()->assertJson(['id' => $a->id, 'batch_number' => 'RM-1/P03']);

        $this->postJson(route('trace.resolve'), ['code' => $a->qr_url])->assertOk()->assertJson(['qr_code' => $a->qr_code]);
        $this->postJson(route('trace.resolve'), ['code' => "https://evil.example/trace/batch/{$a->qr_code}"])->assertStatus(422);

        $this->getJson(route('api.batch.lookup', $b->qr_code))->assertOk()->assertJson(['id' => $b->id]);
        $this->getJson(route('api.batch.lookup', substr($a->qr_code, 0, 7)))->assertNotFound();
        $this->getJson(route('api.batch.lookup', 'RM-1'))->assertNotFound();
    }
}
