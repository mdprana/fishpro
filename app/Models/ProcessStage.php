<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProcessStage extends Model
{
    use HasFactory;

    /** Kode tahap baku, dipakai di controller & view agar tidak salah ketik. */
    public const CUTTING  = 'cutting';
    public const TRIMMING = 'trimming';
    public const STEAK    = 'steak';

    protected $fillable = [
        'code', 'short_code', 'name', 'icon', 'color',
        'sequence', 'requires_product_type', 'is_final_stage', 'is_active',
    ];

    protected $casts = [
        'sequence'              => 'integer',
        'requires_product_type' => 'boolean',
        'is_final_stage'        => 'boolean',
        'is_active'             => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sequence');
    }

    /** Tahap sebelum ini (sumber input). Null berarti inputnya bahan baku dari receiving. */
    public function previous(): ?self
    {
        return static::active()->where('sequence', '<', $this->sequence)
            ->orderByDesc('sequence')->first();
    }

    public function next(): ?self
    {
        return static::active()->where('sequence', '>', $this->sequence)
            ->orderBy('sequence')->first();
    }

    public function isFirst(): bool
    {
        return $this->previous() === null;
    }
}
