<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Berapa kg dari satu batch masukan menyumbang ke satu batch keluaran.
 * Inilah tulang punggung penelusuran: tanpa baris ini, lot hasil gabungan
 * kehilangan rincian asal-usulnya.
 */
class BatchStageLink extends Model
{
    protected $fillable = [
        'batch_stage_record_id', 'batch_stage_output_id',
        'source_batch_id', 'output_batch_id',
        'contributed_kg', 'contribution_pct', 'allocation_method',
    ];

    protected $casts = [
        'contributed_kg'   => 'decimal:3',
        'contribution_pct' => 'decimal:3',
    ];

    public function record(): BelongsTo      { return $this->belongsTo(BatchStageRecord::class, 'batch_stage_record_id'); }
    public function output(): BelongsTo      { return $this->belongsTo(BatchStageOutput::class, 'batch_stage_output_id'); }
    public function sourceBatch(): BelongsTo { return $this->belongsTo(ProductionBatch::class, 'source_batch_id'); }
    public function outputBatch(): BelongsTo { return $this->belongsTo(ProductionBatch::class, 'output_batch_id'); }
}
