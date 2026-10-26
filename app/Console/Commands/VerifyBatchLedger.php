<?php

namespace App\Console\Commands;

use App\Services\StockService;
use Illuminate\Console\Command;

/**
 * Audit integritas: saldo cache tiap batch harus sama dengan hasil penjumlahan
 * ledger-nya. Kalau perintah ini melaporkan selisih, ada jalur kode yang
 * mengubah current_weight_kg tanpa menulis batch_movements.
 */
class VerifyBatchLedger extends Command
{
    protected $signature = 'batch:verify {--fix : Perbaiki cache yang melenceng dari ledger}';

    protected $description = 'Periksa saldo batch terhadap ledger batch_movements';

    public function handle(StockService $stock): int
    {
        $mismatches = $stock->findLedgerMismatches();

        if ($mismatches->isEmpty()) {
            $this->info('Semua saldo batch cocok dengan ledger.');
            return self::SUCCESS;
        }

        $this->error("Ditemukan {$mismatches->count()} batch yang saldonya tidak cocok dengan ledger:");

        $this->table(
            ['ID', 'Nomor Batch', 'Saldo Tersimpan', 'Saldo Ledger', 'Selisih'],
            $mismatches->map(fn ($row) => [
                $row->id,
                $row->batch_number,
                number_format((float) $row->current_weight_kg, 3),
                number_format((float) $row->ledger_balance, 3),
                number_format((float) $row->current_weight_kg - (float) $row->ledger_balance, 3),
            ])->all()
        );

        if (!$this->option('fix')) {
            $this->line('Jalankan ulang dengan --fix untuk menyelaraskan cache dari ledger.');
            return self::FAILURE;
        }

        foreach ($mismatches as $row) {
            \App\Models\ProductionBatch::find($row->id)?->recalcFromLedger();
        }

        $this->info("{$mismatches->count()} batch diselaraskan ulang dari ledger.");

        return self::SUCCESS;
    }
}
