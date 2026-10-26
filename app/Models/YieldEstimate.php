<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YieldEstimate extends Model
{
    protected $fillable = ['material_subgroup_id','product_type_id','percentage'];
    protected $casts    = ['percentage' => 'decimal:2'];

    public function subgroup(): BelongsTo
    {
        return $this->belongsTo(MaterialSubgroup::class, 'material_subgroup_id');
    }

    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class, 'product_type_id');
    }
}
