<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BatchStageOutput extends Model
{
    /** Kategori limbah baku di lantai produksi ikan. */
    public const WASTE_CATEGORIES = [
        'kepala'    => 'Kepala',
        'isi_perut' => 'Isi Perut',
        'kulit'     => 'Kulit',
        'duri'      => 'Duri / Tulang',
        'darah'     => 'Darah',
        'serutan'   => 'Serutan / Trimming Halus',
        'lainnya'   => 'Lain-lain',
    ];

    protected $fillable = [
        'batch_stage_record_id', 'output_batch_id', 'output_type', 'waste_category',
        'product_type_id', 'output_weight_kg', 'container_type', 'container_count', 'notes',
    ];

    protected $casts = [
        'output_weight_kg' => 'decimal:3',
        'container_count'  => 'integer',
    ];

    public function record(): BelongsTo      { return $this->belongsTo(BatchStageRecord::class, 'batch_stage_record_id'); }
    public function outputBatch(): BelongsTo { return $this->belongsTo(ProductionBatch::class, 'output_batch_id'); }
    public function productType(): BelongsTo { return $this->belongsTo(ProductType::class); }
    public function links(): HasMany         { return $this->hasMany(BatchStageLink::class, 'batch_stage_output_id'); }

    public function getWasteCategoryLabelAttribute(): ?string
    {
        return $this->waste_category
            ? (self::WASTE_CATEGORIES[$this->waste_category] ?? $this->waste_category)
            : null;
    }

    public function getLabelAttribute(): string
    {
        return match ($this->output_type) {
            'waste'     => 'Limbah: ' . ($this->waste_category_label ?? '-'),
            'sampingan' => $this->productType?->name ?? 'Sampingan',
            default     => $this->productType?->name ?? 'Hasil Utama',
        };
    }
}
