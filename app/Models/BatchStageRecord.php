<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BatchStageRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'record_code', 'process_stage_id', 'purchase_order_id',
        'process_date', 'start_time', 'end_time',
        'operator_name', 'shift', 'line_name',
        'total_input_kg', 'total_output_kg', 'total_waste_kg', 'yield_pct',
        'status', 'voided_by', 'voided_at', 'void_reason',
        'notes', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'process_date'    => 'date',
        'total_input_kg'  => 'decimal:3',
        'total_output_kg' => 'decimal:3',
        'total_waste_kg'  => 'decimal:3',
        'yield_pct'       => 'decimal:2',
        'voided_at'       => 'datetime',
    ];

    public function stage(): BelongsTo         { return $this->belongsTo(ProcessStage::class, 'process_stage_id'); }
    public function purchaseOrder(): BelongsTo { return $this->belongsTo(PurchaseOrder::class); }
    public function creator(): BelongsTo       { return $this->belongsTo(User::class, 'created_by'); }
    public function voider(): BelongsTo        { return $this->belongsTo(User::class, 'voided_by'); }

    public function inputs(): HasMany  { return $this->hasMany(BatchStageInput::class); }
    public function outputs(): HasMany { return $this->hasMany(BatchStageOutput::class); }
    public function links(): HasMany   { return $this->hasMany(BatchStageLink::class); }

    public function movements(): HasMany
    {
        return $this->hasMany(BatchMovement::class, 'batch_stage_record_id');
    }

    /** Batch yang dihasilkan sesi ini. */
    public function producedBatches(): HasMany
    {
        return $this->hasMany(ProductionBatch::class, 'batch_stage_record_id');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    // ── Perhitungan ───────────────────────────────────────────────

    /** Output utama saja. Inilah yang dibandingkan dengan standar yield. */
    public function getMainOutputKgAttribute(): float
    {
        return round((float) $this->outputs()->where('output_type', 'utama')->sum('output_weight_kg'), 3);
    }

    public function getByproductKgAttribute(): float
    {
        return round((float) $this->outputs()->where('output_type', 'sampingan')->sum('output_weight_kg'), 3);
    }

    /**
     * Berat yang tidak tercatat ke mana pun (input - output - limbah).
     * Ditampilkan apa adanya, bukan disembunyikan, karena selisih besar
     * biasanya berarti ada penimbangan yang terlewat dicatat.
     */
    public function getUnaccountedKgAttribute(): float
    {
        return round(
            (float) $this->total_input_kg - (float) $this->total_output_kg - (float) $this->total_waste_kg,
            3
        );
    }

    public function getWastePctAttribute(): float
    {
        $input = (float) $this->total_input_kg;

        return $input > 0 ? round(((float) $this->total_waste_kg / $input) * 100, 2) : 0;
    }

    public function getIsVoidedAttribute(): bool
    {
        return $this->status === 'voided';
    }
}
