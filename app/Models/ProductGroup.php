<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductGroup extends Model
{
    protected $fillable = ['name','color','is_active'];
    protected $casts    = ['is_active' => 'boolean'];

    public function productTypes(): HasMany
    {
        return $this->hasMany(ProductType::class, 'product_group_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
