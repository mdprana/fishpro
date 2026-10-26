<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YieldEstimateLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['material_subgroup_id', 'previous', 'snapshot', 'created_by'];

    protected $casts = [
        'previous' => 'array',
        'snapshot' => 'array',
    ];

    public function subgroup(): BelongsTo
    {
        return $this->belongsTo(MaterialSubgroup::class, 'material_subgroup_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
