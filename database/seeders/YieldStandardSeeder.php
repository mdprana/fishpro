<?php

namespace Database\Seeders;

use App\Models\MaterialSubgroup;
use App\Models\ProcessStage;
use App\Models\YieldStandard;
use Illuminate\Database\Seeder;

/**
 * Toleransi penyusutan awal. Angka-angka ini adalah titik mulai yang wajar
 * untuk loin tuna; pabrik menyesuaikannya lewat menu Standar Yield, dan tiap
 * penyesuaian tersimpan sebagai versi baru.
 */
class YieldStandardSeeder extends Seeder
{
    public function run(): void
    {
        $stages = ProcessStage::pluck('id', 'code');

        // [kode tahap, minimal, perhatian, target]
        $umum = [
            [ProcessStage::CUTTING,  70, 75, 80],
            [ProcessStage::TRIMMING, 85, 88, 92],
            [ProcessStage::STEAK,    92, 94, 96],
        ];

        foreach ($umum as [$stageCode, $min, $warn, $target]) {
            if (!isset($stages[$stageCode])) {
                continue;
            }

            YieldStandard::updateOrCreate(
                [
                    'material_subgroup_id' => null,   // berlaku umum
                    'process_stage_id'     => $stages[$stageCode],
                    'scope'                => 'stage',
                    'effective_to'         => null,
                ],
                [
                    'min_yield_pct'    => $min,
                    'warn_yield_pct'   => $warn,
                    'target_yield_pct' => $target,
                    'effective_from'   => now()->startOfYear(),
                    'is_active'        => true,
                    'notes'            => 'Standar awal pabrik.',
                ]
            );
        }

        // Contoh standar khusus: yang lebih spesifik mengalahkan yang umum.
        $yfw = MaterialSubgroup::where('code', 'YFW')->first();

        if ($yfw && isset($stages[ProcessStage::TRIMMING])) {
            YieldStandard::updateOrCreate(
                [
                    'material_subgroup_id' => $yfw->id,
                    'process_stage_id'     => $stages[ProcessStage::TRIMMING],
                    'scope'                => 'stage',
                    'effective_to'         => null,
                ],
                [
                    'min_yield_pct'    => 85,
                    'warn_yield_pct'   => 89,
                    'target_yield_pct' => 93,
                    'effective_from'   => now()->startOfYear(),
                    'is_active'        => true,
                    'notes'            => 'Loin eropa warna lebih bersih, susutnya lebih kecil.',
                ]
            );
        }
    }
}
