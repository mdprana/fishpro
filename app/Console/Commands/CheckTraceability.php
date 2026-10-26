<?php

namespace App\Console\Commands;

use App\Models\PackingBox;
use App\Models\ProductionBatch;
use App\Models\PurchaseOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Audit penelusuran sebuah PO:
 *  1. komposisi tiap box harus berjumlah sama dengan berat nettonya
 *  2. total yang diambil dari sebuah batch tidak boleh melebihi isinya
 *  3. tiap box harus bisa menyebut asal-usulnya sampai batch induk
 */
class CheckTraceability extends Command
{
    protected $signature = 'trace:check {po : Nomor PO}';

    protected $description = 'Periksa keutuhan penelusuran box pada sebuah PO';

    public function handle(): int
    {
        $po = PurchaseOrder::where('po_number', $this->argument('po'))->first();

        if (!$po) {
            $this->error("PO {$this->argument('po')} tidak ditemukan.");
            return self::FAILURE;
        }

        $masalah = 0;

        // 1. Komposisi box vs berat netto
        $selisih = DB::table('packing_boxes as b')
            ->leftJoin('packing_box_sources as s', 's.packing_box_id', '=', 'b.id')
            ->where('b.purchase_order_id', $po->id)
            ->where('b.status', '<>', 'voided')
            ->groupBy('b.id', 'b.box_code', 'b.net_weight_kg')
            ->havingRaw('ABS(b.net_weight_kg - COALESCE(SUM(s.contributed_kg), 0)) > 0.01')
            ->get(['b.box_code', 'b.net_weight_kg', DB::raw('COALESCE(SUM(s.contributed_kg),0) as komposisi')]);

        if ($selisih->isNotEmpty()) {
            $masalah += $selisih->count();
            $this->error("{$selisih->count()} box komposisinya tidak sama dengan berat netto:");
            $this->table(['Box', 'Netto', 'Komposisi'], $selisih->map(fn ($r) => [
                $r->box_code, $r->net_weight_kg, $r->komposisi,
            ])->all());
        } else {
            $this->info('Komposisi semua box cocok dengan berat nettonya.');
        }

        // 2. Batch tidak boleh diambil melebihi isinya
        $lebih = DB::table('production_batches as b')
            ->join('packing_box_sources as s', 's.batch_id', '=', 'b.id')
            ->join('packing_boxes as pb', function ($j) {
                $j->on('pb.id', '=', 's.packing_box_id')->where('pb.status', '<>', 'voided');
            })
            ->where('b.purchase_order_id', $po->id)
            ->groupBy('b.id', 'b.batch_number', 'b.initial_weight_kg')
            ->havingRaw('SUM(s.contributed_kg) > b.initial_weight_kg + 0.01')
            ->get(['b.batch_number', 'b.initial_weight_kg', DB::raw('SUM(s.contributed_kg) as terpakai')]);

        if ($lebih->isNotEmpty()) {
            $masalah += $lebih->count();
            $this->error("{$lebih->count()} batch terpakai melebihi isinya:");
            $this->table(['Batch', 'Isi', 'Terpakai'], $lebih->map(fn ($r) => [
                $r->batch_number, $r->initial_weight_kg, $r->terpakai,
            ])->all());
        } else {
            $this->info('Tidak ada batch yang terpakai melebihi isinya.');
        }

        // 3. Setiap box harus bisa menyebut batch induknya
        $yatim = PackingBox::where('purchase_order_id', $po->id)
            ->where('status', '<>', 'voided')
            ->whereDoesntHave('sources', fn ($q) => $q->whereNotNull('root_batch_id'))
            ->count();

        if ($yatim > 0) {
            $masalah += $yatim;
            $this->error("{$yatim} box tidak bisa ditelusuri ke batch induk mana pun.");
        } else {
            $this->info('Semua box bisa ditelusuri sampai batch induk.');
        }

        // Ringkasan
        $this->newLine();
        $this->line("PO           : {$po->po_number} ({$po->buyer_name})");
        $this->line("Bahan baku   : " . number_format($po->total_received_kg, 2) . ' kg');
        $this->line("Hasil packing: " . number_format($po->total_packed_kg, 2) . " kg ({$po->total_packed_box} box)");
        $this->line("Recovery     : {$po->recovery_pct}%");
        $this->line("Batch induk  : " . ProductionBatch::where('purchase_order_id', $po->id)->roots()->count());

        return $masalah === 0 ? self::SUCCESS : self::FAILURE;
    }
}
