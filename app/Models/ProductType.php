<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductType extends Model
{
    protected $fillable = ['product_group_id','name','code','color','is_active'];
    protected $casts    = ['is_active' => 'boolean'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(ProductGroup::class, 'product_group_id');
    }

    public function poItems(): HasMany { return $this->hasMany(PoItem::class,'product_type_id'); }
    public function stageOutputs(): HasMany { return $this->hasMany(BatchStageOutput::class,'product_type_id'); }
    public function packingBoxes(): HasMany { return $this->hasMany(PackingBox::class,'product_type_id'); }
    public function surpluses(): HasMany { return $this->hasMany(PoSurplus::class,'product_type_id'); }
    public function yieldEstimates(): HasMany { return $this->hasMany(YieldEstimate::class,'product_type_id'); }

    /** Subgroups bahan baku yang menghasilkan produk ini */
    public function materialSubgroups(): BelongsToMany
    {
        return $this->belongsToMany(MaterialSubgroup::class, 'yield_estimates', 'product_type_id', 'material_subgroup_id')
            ->withPivot('percentage');
    }

    /** Warna efektif: pakai custom color jika diset, fallback ke warna grup */
    public function getEffectiveColorAttribute(): string
    {
        return $this->color ?? $this->group?->color ?? '#6b7280';
    }

    public function getGroupNameAttribute(): string
    {
        return $this->group?->name ?? 'Tanpa Grup';
    }

    public function scopeActive($query) { return $query->where('is_active', true); }

    /**
     * Estimasi yield untuk pasangan (subgroup, produk) tertentu.
     *
     * Menggantikan yieldEstimatesForGroup() lama yang mengambil satu baris
     * sembarang dari semua subgroup, sehingga angkanya salah begitu satu produk bisa
     * berasal dari lebih dari satu jenis bahan.
     */
    public function yieldEstimateFor(?int $materialSubgroupId): ?YieldEstimate
    {
        if (!$materialSubgroupId) {
            return null;
        }

        return $this->yieldEstimates()->where('material_subgroup_id', $materialSubgroupId)->first();
    }
}
