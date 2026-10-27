<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\BatchStageRecord;
use App\Models\MaterialSubgroup;
use App\Models\PackingBox;
use App\Models\ProductionBatch;
use App\Models\PurchaseOrder;
use App\Models\Receiving;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Penulis audit_events. Hanya field di allowlist yang pernah disimpan:
 * tanpa nama, email, catatan bebas, nomor dokumen, atau payload request.
 * Dipanggil sinkron di dalam transaksi pemanggil, jadi ikut rollback.
 */
class AuditService
{
    public const ENTITIES = [
        ProductionBatch::class  => 'batch',
        Receiving::class        => 'receiving',
        BatchStageRecord::class => 'stage',
        PackingBox::class       => 'box',
        PurchaseOrder::class    => 'po',
        MaterialSubgroup::class => 'subgroup',
    ];

    public const ACTIONS = [
        'stock.movement'        => 'batch',
        'receiving.create'      => 'receiving',
        'receiving.void'        => 'receiving',
        'stage.create'          => 'stage',
        'stage.void'            => 'stage',
        'box.create'            => 'box',
        'box.adjust'            => 'box',
        'box.void'              => 'box',
        'po.create'             => 'po',
        'po.update'             => 'po',
        'po.delete'             => 'po',
        'stock.minimum_changed' => 'subgroup',
    ];

    public const FIELDS = [
        'batch'     => ['current_weight_kg', 'movement_type', 'direction', 'weight_kg', 'receiving_id', 'batch_stage_record_id', 'packing_box_id'],
        'receiving' => ['purchase_order_id', 'material_subgroup_id', 'supplier_id', 'weight_kg', 'container_count', 'split_per_container', 'received_date', 'deleted'],
        'stage'     => ['process_stage_id', 'purchase_order_id', 'total_input_kg', 'total_output_kg', 'status'],
        'box'       => ['purchase_order_id', 'product_type_id', 'net_weight_kg', 'status'],
        'po'        => ['deadline', 'status', 'items', 'deleted'],
        'subgroup'  => ['minimum_stock_kg'],
    ];

    public static function requireTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('Audit hanya boleh ditulis di dalam transaksi DB.');
        }
    }

    /** Potret allowlist dari model yang sudah dikunci/terbaru. */
    public function snapshot(Model $model): array
    {
        $entity = self::ENTITIES[$model::class];
        $row    = [];

        foreach (self::FIELDS[$entity] as $field) {
            if ($field === 'deleted') {
                $row['deleted'] = $model->trashed();
            } elseif ($field === 'items') {
                $row['items'] = $model->items()->orderBy('id')->get()->map(fn ($i) => [
                    'product_type_id'  => (int) $i->product_type_id,
                    'target_weight_kg' => (string) $i->target_weight_kg,
                    'target_box'       => (int) $i->target_box,
                ])->all();
            } elseif (array_key_exists($field, $model->getAttributes())) {
                $value = $model->getAttribute($field);
                $row[$field] = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value;
            }
        }

        return $row;
    }

    /** Tulis satu event; null bila tidak ada perubahan pada field allowlist. */
    public function record(string $action, Model $entity, ?array $before, ?array $after, ?int $movementId = null): ?AuditEvent
    {
        self::requireTransaction();

        $type = self::ACTIONS[$action] ?? throw new \InvalidArgumentException("Aksi audit tidak dikenal: {$action}");

        if ((self::ENTITIES[$entity::class] ?? null) !== $type) {
            throw new \InvalidArgumentException("Entitas tidak cocok untuk aksi {$action}.");
        }

        $allowed = array_flip(self::FIELDS[$type]);
        $before  = $before === null ? null : array_intersect_key($before, $allowed);
        $after   = $after === null ? null : array_intersect_key($after, $allowed);

        if ($before !== null && $before === $after) {
            return null;
        }

        return AuditEvent::create([
            'actor_id'    => auth()->id(),
            'recorded_at' => now()->utc()->format('Y-m-d H:i:s'),
            'entity_type' => $type,
            'entity_id'   => $entity->getKey(),
            'action'      => $action,
            'movement_id' => $movementId,
            'before'      => $before,
            'after'       => $after,
        ]);
    }
}
