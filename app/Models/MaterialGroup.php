<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialGroup extends Model
{
    protected $fillable = ['name','unit','color','description','is_active'];
    protected $casts    = ['is_active' => 'boolean'];

    public function subgroups(): HasMany
    {
        return $this->hasMany(MaterialSubgroup::class, 'material_group_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Total stok semua subgroup di grup ini (kg) */
    public function getTotalStockAttribute(): float
    {
        return (float) $this->subgroups->sum(fn($sg) => $sg->stock?->current_stock_kg ?? 0);
    }
}
