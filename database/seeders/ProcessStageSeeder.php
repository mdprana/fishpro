<?php

namespace Database\Seeders;

use App\Models\ProcessStage;
use Illuminate\Database\Seeder;

/**
 * Tiga tahap baku pabrik. `sequence` memakai kelipatan 10 supaya tahap baru
 * (mis. "Sortir" di antara Potong dan Trimming) bisa disisipkan tanpa
 * menomori ulang yang sudah ada.
 */
class ProcessStageSeeder extends Seeder
{
    public function run(): void
    {
        $stages = [
            [
                'code'                  => ProcessStage::CUTTING,
                'short_code'            => 'CT',
                'name'                  => 'Potong (Cutting)',
                'icon'                  => 'bi-scissors',
                'color'                 => '#2563eb',
                'sequence'              => 10,
                // Output potong masih "ikan tanpa kepala & isi perut", belum
                // dipilah menjadi tipe produk jual.
                'requires_product_type' => false,
                'is_final_stage'        => false,
            ],
            [
                'code'                  => ProcessStage::TRIMMING,
                'short_code'            => 'TR',
                'name'                  => 'Trimming',
                'icon'                  => 'bi-droplet-half',
                'color'                 => '#16a34a',
                'sequence'              => 20,
                'requires_product_type' => true,
                'is_final_stage'        => false,
            ],
            [
                'code'                  => ProcessStage::STEAK,
                'short_code'            => 'ST',
                'name'                  => 'Potong Steak',
                'icon'                  => 'bi-grid-3x3',
                'color'                 => '#f59e0b',
                'sequence'              => 30,
                'requires_product_type' => true,
                'is_final_stage'        => true,   // outputnya yang masuk packing
            ],
        ];

        foreach ($stages as $stage) {
            ProcessStage::updateOrCreate(['code' => $stage['code']], $stage + ['is_active' => true]);
        }
    }
}
