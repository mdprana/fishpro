<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BatchStageInput extends Model
{
    protected $fillable = ['batch_stage_record_id', 'batch_id', 'input_weight_kg', 'notes'];

    protected $casts = ['input_weight_kg' => 'decimal:3'];

    public function record(): BelongsTo { return $this->belongsTo(BatchStageRecord::class, 'batch_stage_record_id'); }
    public function batch(): BelongsTo  { return $this->belongsTo(ProductionBatch::class, 'batch_id'); }
}
