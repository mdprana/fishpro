<?php

namespace Tests\Feature;

use App\Models\MaterialGroup;
use App\Models\MaterialSubgroup;
use App\Models\ProcessStage;
use App\Models\Supplier;
use App\Services\CodeGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CodeGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private CodeGenerator $codes;
    private Supplier $supplier;
    private MaterialSubgroup $subgroup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->codes = app(CodeGenerator::class);

        $this->supplier = Supplier::create(['code' => 'NJY', 'name' => 'UD Nelayan Jaya']);

        $group = MaterialGroup::create(['name' => 'YF LOIN', 'unit' => 'kg', 'color' => '#2563eb']);
        $this->subgroup = MaterialSubgroup::create([
            'material_group_id' => $group->id,
            'name' => 'YF LOIN EROPA WARNA',
            'code' => 'YFW',
        ]);
    }

    public function test_batch_induk_memakai_format_dan_urutan_yang_benar(): void
    {
        $date = Carbon::parse('2026-09-18');

        $first  = $this->codes->parentLot($this->supplier, $this->subgroup, $date);
        $second = $this->codes->parentLot($this->supplier, $this->subgroup, $date);

        $this->assertSame('RM-260918-NJY-YFW-001', $first);
        $this->assertSame('RM-260918-NJY-YFW-002', $second);
    }

    public function test_urutan_direset_per_tanggal_supplier_dan_subgroup(): void
    {
        $lain = Supplier::create(['code' => 'OCF', 'name' => 'PT Ocean Fresh']);

        $a = $this->codes->parentLot($this->supplier, $this->subgroup, '2026-09-18');
        $b = $this->codes->parentLot($lain, $this->subgroup, '2026-09-18');
        $c = $this->codes->parentLot($this->supplier, $this->subgroup, '2026-09-19');

        $this->assertSame('RM-260918-NJY-YFW-001', $a);
        $this->assertSame('RM-260918-OCF-YFW-001', $b, 'Supplier berbeda punya deret sendiri.');
        $this->assertSame('RM-260919-NJY-YFW-001', $c, 'Tanggal berbeda punya deret sendiri.');
    }

    public function test_seratus_pemanggilan_tidak_menghasilkan_nomor_kembar(): void
    {
        $codes = [];
        for ($i = 0; $i < 100; $i++) {
            $codes[] = $this->codes->parentLot($this->supplier, $this->subgroup, '2026-09-18');
        }

        $this->assertCount(100, array_unique($codes));
        $this->assertSame('RM-260918-NJY-YFW-100', end($codes));
    }

    public function test_nomor_lot_tahap_menempel_pada_induknya(): void
    {
        $cutting  = ProcessStage::create([
            'code' => 'cutting', 'short_code' => 'CT', 'name' => 'Potong', 'sequence' => 10,
        ]);
        $trimming = ProcessStage::create([
            'code' => 'trimming', 'short_code' => 'TR', 'name' => 'Trimming', 'sequence' => 20,
        ]);

        $parent    = 'RM-260918-NJY-YFW-001';
        $container = $this->codes->containerLot($parent, 3);
        $cut       = $this->codes->stageLot($container, $cutting, 1);
        $trim      = $this->codes->stageLot($cut, $trimming, 1);

        $this->assertSame('RM-260918-NJY-YFW-001/P03', $container);
        $this->assertSame('RM-260918-NJY-YFW-001/P03-CT01', $cut);
        $this->assertSame('RM-260918-NJY-YFW-001/P03-CT01-TR01', $trim);

        // Rantai asal-usul harus terbaca dari nomornya sendiri.
        $this->assertStringStartsWith($parent, $trim);
    }

    public function test_kode_record_tahap_dan_lot_gabungan(): void
    {
        $cutting = ProcessStage::create([
            'code' => 'cutting', 'short_code' => 'CT', 'name' => 'Potong', 'sequence' => 10,
        ]);

        $this->assertSame('CUT-260918-0001', $this->codes->stageRecord($cutting, '2026-09-18'));
        $this->assertSame('CUT-260918-0002', $this->codes->stageRecord($cutting, '2026-09-18'));
        $this->assertSame('MIX-260918-CT-001', $this->codes->mixedLot($cutting, '2026-09-18'));
    }

    public function test_token_qr_unik_dan_tanpa_karakter_ambigu(): void
    {
        $tokens = [];
        for ($i = 0; $i < 50; $i++) {
            // Tabel mana pun boleh; yang diuji di sini adalah bentuk & keunikan token.
            $token = $this->codes->qrToken('receivings', 'batch_code');

            $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{8}$/', $token,
                'Token tidak boleh memuat I, L, O, atau U agar tidak tertukar saat diketik ulang.');

            $tokens[] = $token;
        }

        $this->assertCount(50, array_unique($tokens));
    }

    public function test_subgroup_tanpa_kode_tetap_menghasilkan_nomor_yang_valid(): void
    {
        $this->subgroup->update(['code' => null]);

        $code = $this->codes->parentLot($this->supplier, $this->subgroup, '2026-09-18');

        // "YF LOIN EROPA WARNA" -> inisial YLEW
        $this->assertSame('RM-260918-NJY-YLEW-001', $code);
    }
}
