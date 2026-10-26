<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris ledger pergerakan berat batch.
 *
 * Bersifat append-only: baris yang sudah ditulis tidak pernah diubah atau
 * dihapus. Guard di boot() membuat pelanggaran gagal keras saat pengembangan,
 * bukan diam-diam merusak jejak audit di produksi.
 */
class BatchMovement extends Model
{
    /** Tabel ini hanya punya created_at. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'batch_id', 'movement_type', 'direction', 'weight_kg', 'balance_after',
        'batch_stage_record_id', 'packing_box_id', 'receiving_id', 'reversal_of_id',
        'occurred_at', 'note', 'created_by',
    ];

    protected $casts = [
        'weight_kg'     => 'decimal:3',
        'balance_after' => 'decimal:3',
        'occurred_at'   => 'datetime',
    ];

    public const TYPE_RECEIPT       = 'receipt';
    public const TYPE_STAGE_CONSUME = 'stage_consume';
    public const TYPE_STAGE_PRODUCE = 'stage_produce';
    public const TYPE_WASTE         = 'waste';
    public const TYPE_PACK          = 'pack';
    public const TYPE_ADJUSTMENT    = 'adjustment';
    public const TYPE_REVERSAL      = 'reversal';

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \LogicException(
                'batch_movements bersifat append-only. Untuk mengoreksi, tulis baris reversal baru.'
            );
        });

        static::deleting(function () {
            throw new \LogicException(
                'batch_movements bersifat append-only. Baris ledger tidak boleh dihapus.'
            );
        });
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductionBatch::class, 'batch_id');
    }

    public function receiving(): BelongsTo
    {
        return $this->belongsTo(Receiving::class, 'receiving_id');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Berat bertanda: positif untuk masuk, negatif untuk keluar. */
    public function getSignedWeightAttribute(): float
    {
        return (float) $this->weight_kg * ($this->direction === 'in' ? 1 : -1);
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->movement_type) {
            self::TYPE_RECEIPT       => 'Penerimaan',
            self::TYPE_STAGE_CONSUME => 'Dipakai proses',
            self::TYPE_STAGE_PRODUCE => 'Hasil proses',
            self::TYPE_WASTE         => 'Limbah',
            self::TYPE_PACK          => 'Masuk packing',
            self::TYPE_ADJUSTMENT    => 'Koreksi',
            self::TYPE_REVERSAL      => 'Pembatalan',
            default                  => $this->movement_type,
        };
    }
}
