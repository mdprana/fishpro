<?php

namespace App\Services;

use App\Models\MaterialSubgroup;
use App\Models\ProductionBatch;
use App\Models\RawMaterialStock;
use Illuminate\Support\Facades\DB;

/**
 * Menjaga `raw_material_stocks` tetap sinkron dengan kenyataan.
 *
 * Tabel itu kini hanya CACHE: angkanya selalu dihitung ulang dari saldo
 * batch induk yang masih tersedia. Tidak ada lagi jalur tambah/kurang ad-hoc
 * yang dulu membuat stok dihitung dua kali atau tidak terkoreksi saat dihapus.
 */
class StockService
{
    /**
     * Hitung ulang cache stok satu subgroup dari saldo batch.
     *
     * Dijumlahkan berdasarkan content_type `raw`, bukan level 0. Saat sebuah
     * kiriman dipecah per wadah, isinya pindah ke batch anak dan induknya
     * berstatus `consumed`. Menjumlah level 0 saja akan melaporkan stok nol.
     */
    public function recomputeSubgroup(int $subgroupId): float
    {
        $total = (float) ProductionBatch::query()
            ->where('content_type', 'raw')
            ->where('material_subgroup_id', $subgroupId)
            ->whereIn('status', ['available', 'partial'])
            ->sum('current_weight_kg');

        RawMaterialStock::updateOrCreate(
            ['material_subgroup_id' => $subgroupId],
            ['current_stock_kg' => round($total, 2)]
        );

        return round($total, 2);
    }

    /** Hitung ulang seluruh subgroup. Dipakai perintah `stock:recompute`. */
    public function recomputeAll(): int
    {
        $ids = MaterialSubgroup::pluck('id');

        foreach ($ids as $id) {
            $this->recomputeSubgroup((int) $id);
        }

        return $ids->count();
    }

    /**
     * Hitung ulang saldo satu batch dari ledger, lalu rapikan cache stok
     * subgroup induknya bila batch tersebut memang batch bahan baku.
     */
    public function recomputeBatch(ProductionBatch $batch): float
    {
        $balance = $batch->recalcFromLedger();

        if ($batch->content_type === 'raw' && $batch->material_subgroup_id) {
            $this->recomputeSubgroup((int) $batch->material_subgroup_id);
        }

        return $balance;
    }

    /**
     * Selisih antara cache dan ledger untuk seluruh batch.
     * Mengembalikan baris yang tidak cocok. Idealnya kosong.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function findLedgerMismatches(float $tolerance = 0.005)
    {
        return DB::table('production_batches as b')
            ->leftJoin('batch_movements as m', 'm.batch_id', '=', 'b.id')
            ->groupBy('b.id', 'b.batch_number', 'b.current_weight_kg')
            ->havingRaw(
                'ABS(b.current_weight_kg - COALESCE(SUM(CASE WHEN m.direction = ? THEN m.weight_kg ELSE -m.weight_kg END), 0)) > ?',
                ['in', $tolerance]
            )
            ->get([
                'b.id',
                'b.batch_number',
                'b.current_weight_kg',
                DB::raw('COALESCE(SUM(CASE WHEN m.direction = "in" THEN m.weight_kg ELSE -m.weight_kg END), 0) as ledger_balance'),
            ]);
    }
}
