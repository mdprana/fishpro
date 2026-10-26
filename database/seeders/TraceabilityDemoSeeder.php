<?php

namespace Database\Seeders;

use App\Models\MaterialSubgroup;
use App\Models\ProcessStage;
use App\Models\ProductionBatch;
use App\Models\ProductType;
use App\Models\PurchaseOrder;
use App\Models\Receiving;
use App\Models\Supplier;
use App\Models\User;
use App\Services\BatchService;
use App\Services\PackingService;
use App\Services\StageService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Data contoh rantai penuh: Bongkar → Potong → Trimming → Steak → Packing,
 * lengkap dengan kasus-kasus sulit yang harus tetap bisa ditelusuri:
 *
 *  - dua supplier digabung dalam satu sesi potong (box jadi campuran)
 *  - satu batch dipecah ke beberapa sesi
 *  - satu sesi trimming sengaja di bawah standar, untuk menguji alert
 *  - 500 box dengan berat yang tidak semuanya sama
 *
 * Jalankan setelah DatabaseSeeder:
 *   php artisan db:seed --class=TraceabilityDemoSeeder
 */
class TraceabilityDemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first() ?? User::factory()->create();
        auth()->login($user);   // service memakai auth()->id() untuk jejak audit

        $cutting  = ProcessStage::where('code', ProcessStage::CUTTING)->firstOrFail();
        $trimming = ProcessStage::where('code', ProcessStage::TRIMMING)->firstOrFail();
        $steak    = ProcessStage::where('code', ProcessStage::STEAK)->firstOrFail();

        $subgroup = MaterialSubgroup::where('code', 'YFW')->firstOrFail();
        $produk   = ProductType::where('code', 'YFSTK')->firstOrFail();
        $serbuk   = ProductType::where('code', 'SRBK')->first();

        $njy = Supplier::where('code', 'NJY')->firstOrFail();
        $ocf = Supplier::where('code', 'OCF')->firstOrFail();
        $smr = Supplier::where('code', 'SMR')->firstOrFail();

        $po = PurchaseOrder::updateOrCreate(
            ['po_number' => 'PO-2026-010'],
            [
                'buyer_name' => 'PT Seafood Nusantara',
                'order_date' => Carbon::today()->subDays(12),
                'deadline'   => Carbon::today()->addDays(10),
                'status'     => 'in_progress',
                'created_by' => $user->id,
            ]
        );

        $po->items()->firstOrCreate(
            ['product_type_id' => $produk->id],
            ['target_weight_kg' => 2500, 'target_box' => 500, 'weight_per_box_kg' => 5]
        );

        // ── Hari H: bongkar dari tiga supplier ────────────────────
        $lotNjy = $this->terima($po, $njy, $subgroup, 1800, 30, true,  8, $user);
        $lotOcf = $this->terima($po, $ocf, $subgroup, 1200, 20, false, 8, $user);
        $lotSmr = $this->terima($po, $smr, $subgroup, 900,  15, false, 7, $user);

        // ── H+1: Potong ──────────────────────────────────────────
        // Sesi 1 menggabungkan dua supplier. Inilah yang membuat box
        // hasil akhirnya menjadi campuran.
        $cut1 = $this->stage($cutting, $po, Carbon::today()->subDays(6), 'Tim Potong A', [
            ['batch_id' => $lotOcf->id, 'input_weight_kg' => 1200],
            ['batch_id' => $lotSmr->id, 'input_weight_kg' => 900],
        ], [
            ['output_type' => 'utama', 'output_weight_kg' => 1638, 'container_type' => 'Keranjang', 'container_count' => 28],
        ], [
            ['waste_category' => 'kepala',    'output_weight_kg' => 336],
            ['waste_category' => 'isi_perut', 'output_weight_kg' => 126],
        ]);

        // Batch NJY dipecah ke dua sesi: 1000 kg dulu, sisanya menyusul.
        $anakNjy = ProductionBatch::where('parent_batch_id', $lotNjy->id)->orderBy('id')->get();
        $bagian1 = $anakNjy->take(17);
        $bagian2 = $anakNjy->slice(17);

        $cut2 = $this->stage($cutting, $po, Carbon::today()->subDays(6), 'Tim Potong B',
            $bagian1->map(fn ($b) => ['batch_id' => $b->id, 'input_weight_kg' => (float) $b->current_weight_kg])->all(),
            [['output_type' => 'utama', 'output_weight_kg' => round($bagian1->sum(fn ($b) => (float) $b->current_weight_kg) * 0.78, 3)]],
            [['waste_category' => 'kepala', 'output_weight_kg' => round($bagian1->sum(fn ($b) => (float) $b->current_weight_kg) * 0.22, 3)]]
        );

        $cut3 = $this->stage($cutting, $po, Carbon::today()->subDays(5), 'Tim Potong B',
            $bagian2->map(fn ($b) => ['batch_id' => $b->id, 'input_weight_kg' => (float) $b->current_weight_kg])->all(),
            [['output_type' => 'utama', 'output_weight_kg' => round($bagian2->sum(fn ($b) => (float) $b->current_weight_kg) * 0.78, 3)]],
            [['waste_category' => 'kepala', 'output_weight_kg' => round($bagian2->sum(fn ($b) => (float) $b->current_weight_kg) * 0.22, 3)]]
        );

        // ── H+2: Trimming ────────────────────────────────────────
        $trim1 = $this->trimming($trimming, $po, Carbon::today()->subDays(4), 'Tim Trimming A', $cut1, $produk, $serbuk, 0.86);
        $trim2 = $this->trimming($trimming, $po, Carbon::today()->subDays(4), 'Tim Trimming B', $cut2, $produk, $serbuk, 0.86);
        // Sesi ini sengaja 68%, di bawah standar 85%, untuk memicu alert.
        $trim3 = $this->trimming($trimming, $po, Carbon::today()->subDays(3), 'Tim Trimming C', $cut3, $produk, $serbuk, 0.68,
            'Mesin trimming sempat macet, banyak daging terbuang.');

        // ── H+3: Potong Steak ────────────────────────────────────
        $steakLot = $this->stage($steak, $po, Carbon::today()->subDays(2), 'Tim Steak',
            collect([$trim1, $trim2, $trim3])->map(fn ($b) => [
                'batch_id' => $b->id, 'input_weight_kg' => (float) $b->current_weight_kg,
            ])->all(),
            [[
                'output_type'     => 'utama',
                'product_type_id' => $produk->id,
                'output_weight_kg' => round(collect([$trim1, $trim2, $trim3])->sum(fn ($b) => (float) $b->current_weight_kg) * 0.93, 3),
                'container_type'  => 'Box Sementara',
            ]],
            [['waste_category' => 'serutan',
              'output_weight_kg' => round(collect([$trim1, $trim2, $trim3])->sum(fn ($b) => (float) $b->current_weight_kg) * 0.07, 3)]]
        );

        // ── H+4: Packing 500 box ─────────────────────────────────
        $tersedia = (float) $steakLot->fresh()->current_weight_kg;
        $jumlahBox = 500;
        $perBox    = floor(($tersedia / $jumlahBox) * 1000) / 1000;

        $hasil = app(PackingService::class)->bulkGenerate(
            $po, $produk,
            [['batch_id' => $steakLot->id, 'kg' => round($perBox * $jumlahBox, 3)]],
            $jumlahBox, $perBox,
            ['packing_date' => Carbon::today()->subDay(), 'operator_name' => 'Tim Packing']
        );

        // Beberapa box dikoreksi beratnya, seperti kenyataan di timbangan.
        foreach ($hasil['boxes']->take(12) as $i => $box) {
            app(PackingService::class)->adjustBoxWeight(
                $box,
                round($perBox + (($i % 3) - 1) * 0.15, 3),
                'Koreksi timbangan saat QC'
            );
        }

        $this->command?->info("Demo siap: PO {$po->po_number}, {$hasil['boxes']->count()} box, "
            . 'batch induk dari 3 supplier, 1 sesi trimming di bawah standar.');
    }

    private function terima(
        PurchaseOrder $po, Supplier $supplier, MaterialSubgroup $subgroup,
        float $kg, int $wadah, bool $split, int $hariLalu, User $user
    ): ProductionBatch {
        $receiving = Receiving::create([
            'material_subgroup_id' => $subgroup->id,
            'purchase_order_id'    => $po->id,
            'supplier_id'          => $supplier->id,
            'weight_kg'            => $kg,
            'container_type'       => 'Keranjang',
            'container_count'      => $wadah,
            'split_per_container'  => $split,
            'doc_number'           => 'SJ-' . strtoupper($supplier->code) . '-' . Carbon::today()->subDays($hariLalu)->format('ymd'),
            'received_date'        => Carbon::today()->subDays($hariLalu),
            'created_by'           => $user->id,
        ]);

        $batches = app(BatchService::class)->createFromReceiving($receiving);

        // Kembalikan batch induk supaya pemanggil bisa memilih sendiri
        // apakah memakai induk utuh atau anak-anaknya.
        return ProductionBatch::where('receiving_id', $receiving->id)->where('level', 0)->firstOrFail();
    }

    private function stage(
        ProcessStage $stage, PurchaseOrder $po, Carbon $tanggal, string $operator,
        array $inputs, array $outputs, array $wastes = [], ?string $notes = null
    ): ProductionBatch {
        $hasil = app(StageService::class)->record($stage, [
            'purchase_order_id' => $po->id,
            'process_date'      => $tanggal->toDateString(),
            'operator_name'     => $operator,
            'notes'             => $notes,
            'inputs'            => $inputs,
            'outputs'           => $outputs,
            'wastes'            => $wastes,
        ]);

        return $hasil['batches']->first();
    }

    /** Trimming: daging bersih + serbuk sebagai hasil sampingan + limbah. */
    private function trimming(
        ProcessStage $stage, PurchaseOrder $po, Carbon $tanggal, string $operator,
        ProductionBatch $sumber, ProductType $produk, ?ProductType $sampingan,
        float $rasio, ?string $notes = null
    ): ProductionBatch {
        $masuk  = (float) $sumber->fresh()->current_weight_kg;
        $bersih = round($masuk * $rasio, 3);
        $serbuk = $sampingan ? round($masuk * 0.04, 3) : 0.0;
        $limbah = round($masuk - $bersih - $serbuk, 3);

        $outputs = [[
            'output_type'      => 'utama',
            'product_type_id'  => $produk->id,
            'output_weight_kg' => $bersih,
            'container_type'   => 'Keranjang',
        ]];

        if ($sampingan && $serbuk > 0) {
            $outputs[] = [
                'output_type'      => 'sampingan',
                'product_type_id'  => $sampingan->id,
                'output_weight_kg' => $serbuk,
            ];
        }

        return $this->stage($stage, $po, $tanggal, $operator,
            [['batch_id' => $sumber->id, 'input_weight_kg' => $masuk]],
            $outputs,
            [
                ['waste_category' => 'kulit', 'output_weight_kg' => round($limbah * 0.5, 3)],
                ['waste_category' => 'duri',  'output_weight_kg' => round($limbah * 0.3, 3)],
                ['waste_category' => 'darah', 'output_weight_kg' => round($limbah - round($limbah * 0.5, 3) - round($limbah * 0.3, 3), 3)],
            ],
            $notes
        );
    }
}
