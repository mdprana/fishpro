<?php

namespace App\Console\Commands;

use App\Services\StockService;
use Illuminate\Console\Command;

/**
 * Susun ulang cache `raw_material_stocks` dari saldo batch.
 * Aman dijalankan kapan saja karena tabel itu hasil turunan, bukan sumber data.
 */
class RecomputeStock extends Command
{
    protected $signature = 'stock:recompute';

    protected $description = 'Hitung ulang stok bahan baku dari saldo batch';

    public function handle(StockService $stock): int
    {
        $jumlah = $stock->recomputeAll();

        $this->info("Stok {$jumlah} subgroup dihitung ulang dari saldo batch.");

        return self::SUCCESS;
    }
}
