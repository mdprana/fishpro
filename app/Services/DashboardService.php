<?php
namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\RawMaterialStock;
use App\Models\MaterialGroup;
use App\Models\BatchStageOutput;
use App\Models\BatchStageRecord;
use App\Models\PoSurplus;
use Carbon\Carbon;

class DashboardService
{
    public function getSummary(): array
    {
        $activePOs = PurchaseOrder::active()
            ->with(['items.productType.group','items.packingBoxes','stageRecords'])
            ->get();

        $alerts = [];

        foreach ($activePOs as $po) {
            foreach ($po->items as $item) {
                $typeId      = $item->product_type_id;
                $targetKg    = (float)$item->target_weight_kg;
                $targetBox   = (int)$item->target_box;
                $productName = $item->productType->name;
                $color       = $item->productType->effective_color;
                $daysLeft    = $po->days_remaining_raw;

                $processedKg = $this->processedKgFor($po, $typeId);
                $packedBox   = (int)$item->packed_box;

                $processPct      = $targetKg > 0 ? round(($processedKg/$targetKg)*100,1) : 0;
                $packPct         = $targetBox > 0 ? round(($packedBox/$targetBox)*100,1) : 0;
                $kurangProsesPct = max(0, 100 - $processPct);
                $kurangPackPct   = max(0, 100 - $packPct);

                if ($kurangProsesPct > 0) {
                    $alerts[] = [
                        'type'       => $kurangProsesPct > 50 ? 'danger' : 'warning',
                        'po_id'      => $po->id,
                        'po_number'  => $po->po_number,
                        'product'    => $productName,
                        'color'      => $color,
                        'category'   => 'proses',
                        'message'    => "Proses {$productName}: {$processPct}%, kurang {$kurangProsesPct}%",
                        'done_pct'   => $processPct,
                        'kurang_pct' => $kurangProsesPct,
                        'days_left'  => $daysLeft,
                    ];
                }

                if ($kurangPackPct > 0) {
                    $alerts[] = [
                        'type'       => $kurangPackPct > 50 ? 'danger' : 'warning',
                        'po_id'      => $po->id,
                        'po_number'  => $po->po_number,
                        'product'    => $productName,
                        'color'      => $color,
                        'category'   => 'packing',
                        'message'    => "Packing {$productName}: {$packPct}%, kurang {$kurangPackPct}%",
                        'done_pct'   => $packPct,
                        'kurang_pct' => $kurangPackPct,
                        'days_left'  => $daysLeft,
                    ];
                }
            }
        }

        // Stok bahan baku rendah
        $lowStocks = RawMaterialStock::with('subgroup.group')->where('current_stock_kg','<',10)->get();
        foreach ($lowStocks as $s) {
            $alerts[] = [
                'type'       => 'warning',
                'po_id'      => null,
                'po_number'  => 'STOK',
                'product'    => $s->subgroup->name,
                'color'      => $s->subgroup->group->color ?? '#6b7280',
                'category'   => 'stok',
                'message'    => "Stok {$s->subgroup->name} hampir habis: {$s->current_stock_kg} kg",
                'done_pct'   => 0,
                'kurang_pct' => 100,
                'days_left'  => 99,
            ];
        }

        return [
            'total_active_po'  => $activePOs->count(),
            'total_alerts'     => count($alerts),
            'total_packed_box' => \App\Models\PackingBox::active()->count(),
            'today_output_kg'  => round((float)BatchStageRecord::completed()
                ->whereDate('process_date', today())->sum('total_output_kg'), 2),
            'alerts'           => $alerts,
            'active_pos'       => $activePOs,
        ];
    }

    /** Total hasil produksi sebuah produk pada satu PO, lintas seluruh tahap. */
    private function processedKgFor(PurchaseOrder $po, int $productTypeId): float
    {
        return (float) BatchStageOutput::whereIn(
                'batch_stage_record_id',
                $po->stageRecords->where('status','completed')->pluck('id')
            )
            ->where('product_type_id', $productTypeId)
            ->whereIn('output_type', ['utama','sampingan'])
            ->sum('output_weight_kg');
    }

    public function getWeeklyChart(): array
    {
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date   = Carbon::today()->subDays($i);
            $output = BatchStageRecord::completed()->whereDate('process_date', $date)->sum('total_output_kg');
            $data[] = ['date' => $date->format('d M'), 'output_kg' => round($output,2)];
        }
        return $data;
    }

    public function getDailyStats(): array
    {
        $recordIds = BatchStageRecord::completed()->whereDate('process_date', today())->pluck('id');

        return BatchStageOutput::whereIn('batch_stage_record_id', $recordIds)
            ->whereIn('output_type', ['utama','sampingan'])
            ->whereNotNull('product_type_id')
            ->with('productType.group')
            ->selectRaw('product_type_id, SUM(output_weight_kg) as total_kg, COUNT(DISTINCT batch_stage_record_id) as sessions')
            ->groupBy('product_type_id')->get()
            ->map(fn($r) => [
                'product'  => $r->productType->name,
                'color'    => $r->productType->effective_color,
                'total_kg' => round($r->total_kg,2),
                'sessions' => $r->sessions,
            ])->toArray();
    }

    public function getStockSummary(): array
    {
        return MaterialGroup::active()
            ->with(['subgroups' => fn($q) => $q->active()->with('stock')])
            ->get()
            ->flatMap(function ($g) {
                return $g->subgroups->map(fn($s) => [
                    'group'           => $g->name,
                    'subgroup'        => $s->name,
                    'color'           => $g->color,
                    'current_stock_kg'=> (float)($s->stock?->current_stock_kg ?? 0),
                    'alert'           => ($s->stock?->current_stock_kg ?? 0) < 10 ? 'danger'
                        : (($s->stock?->current_stock_kg ?? 0) < 50 ? 'warning' : 'success'),
                ]);
            })->toArray();
    }

    public function getPOTable(): array
    {
        return PurchaseOrder::active()
            ->with(['items.productType.group','items.packingBoxes','stageRecords'])
            ->get()
            ->map(function ($po) {
                $daysLeft = $po->days_remaining_raw;

                $itemDetails = $po->items->map(function ($item) use ($po) {
                    $typeId      = $item->product_type_id;
                    $targetKg    = (float)$item->target_weight_kg;
                    $targetBox   = (int)$item->target_box;
                    $processedKg = $this->processedKgFor($po, $typeId);
                    $packedBox   = (int)$item->packed_box;
                    $packedKg    = (float)$item->packed_kg;
                    $processPct  = $targetKg > 0 ? round(($processedKg/$targetKg)*100,1) : 0;
                    $packPct     = $targetBox > 0 ? round(($packedBox/$targetBox)*100,1) : 0;

                    return [
                        'name'          => $item->productType->name,
                        'group_name'    => $item->productType->group_name,
                        'color'         => $item->productType->effective_color,
                        'target_kg'     => $targetKg,
                        'target_box'    => $targetBox,
                        'wpb'           => $item->effective_weight_per_box,
                        'processed_kg'  => round($processedKg,2),
                        'process_pct'   => $processPct,
                        'process_alert' => $processPct>=100?'success':($processPct>=75?'info':($processPct>=40?'warning':'danger')),
                        'packed_box'    => $packedBox,
                        'packed_kg'     => round($packedKg,2),
                        'pack_pct'      => $packPct,
                        'pack_alert'    => $packPct>=100?'success':($packPct>=75?'info':($packPct>=40?'warning':'danger')),
                        'remaining_box' => max(0,$targetBox-$packedBox),
                    ];
                });

                return [
                    'id'         => $po->id,
                    'po_number'  => $po->po_number,
                    'buyer_name' => $po->buyer_name,
                    'status'     => $po->status,
                    'deadline'   => $po->deadline->format('d M Y'),
                    'days_left'  => $daysLeft,
                    'is_overdue' => $daysLeft < 0 && $po->status !== 'completed',
                    'items'      => $itemDetails,
                ];
            })->toArray();
    }

    public function getSurpluses(): array
    {
        return PoSurplus::with(['sourcePO','productType.group'])
            ->where('status','available')
            ->orderByDesc('created_at')->get()
            ->map(fn($s) => [
                'code'         => $s->surplus_code,
                'po_number'    => $s->sourcePO->po_number,
                'po_id'        => $s->source_po_id,
                'product'      => $s->productType->name,
                'color'        => $s->productType->effective_color,
                'type_label'   => $s->getTypeLabel(),
                'weight_kg'    => $s->weight_kg,
                'box_count'    => $s->box_count,
                'status_label' => $s->getStatusLabel(),
            ])->toArray();
    }
}
