<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MaterialSubgroup extends Model
{
    protected $fillable = ['material_group_id','name','code','is_active'];
    protected $casts    = ['is_active' => 'boolean'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(MaterialGroup::class, 'material_group_id');
    }

    public function stock(): HasOne
    {
        return $this->hasOne(RawMaterialStock::class, 'material_subgroup_id');
    }

    public function receivings(): HasMany
    {
        return $this->hasMany(Receiving::class, 'material_subgroup_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ProductionBatch::class, 'material_subgroup_id');
    }

    public function yieldStandards(): HasMany
    {
        return $this->hasMany(YieldStandard::class, 'material_subgroup_id');
    }

    /** Sisa bahan baku yang benar-benar ada di batch, bukan angka saldo lepas. */
    public function getAvailableBatchesKgAttribute(): float
    {
        return round((float) $this->batches()
            ->where('content_type', 'raw')
            ->whereIn('status', ['available', 'partial'])
            ->sum('current_weight_kg'), 3);
    }

    /** Estimasi yield → produk (relasi langsung) */
    public function yieldEstimates(): HasMany
    {
        return $this->hasMany(YieldEstimate::class, 'material_subgroup_id');
    }

    /** Produk yang terhubung lewat yield estimate (many-to-many helper) */
    public function productTypes(): BelongsToMany
    {
        return $this->belongsToMany(ProductType::class, 'yield_estimates', 'material_subgroup_id', 'product_type_id')
            ->withPivot('percentage');
    }

    public function getCurrentStockAttribute(): float
    {
        return (float) ($this->stock?->current_stock_kg ?? 0);
    }

    /** Total persentase yield yang sudah dikonfigurasi */
    public function getTotalYieldPctAttribute(): float
    {
        return (float) $this->yieldEstimates()->sum('percentage');
    }

    /**
     * Estimasi output per produk berdasarkan stok saat ini × persentase yield
     * Return: collection of ['product_type' => ProductType, 'percentage' => x, 'estimated_kg' => y]
     */
    public function getEstimatedOutputsAttribute()
    {
        $stock = $this->current_stock;
        return $this->yieldEstimates()->with('productType')->get()->map(function ($ye) use ($stock) {
            return [
                'product_type'  => $ye->productType,
                'percentage'    => (float) $ye->percentage,
                'estimated_kg'  => round($stock * (float)$ye->percentage / 100, 2),
            ];
        });
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
