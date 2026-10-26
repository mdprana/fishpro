<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PackingRun extends Model
{
    protected $fillable = [
        'run_code', 'purchase_order_id', 'po_item_id', 'product_type_id',
        'mode', 'planned_box_count', 'planned_weight_per_box',
        'packing_date', 'operator_name', 'status', 'notes', 'created_by',
    ];

    protected $casts = [
        'packing_date'          => 'date',
        'planned_box_count'     => 'integer',
        'planned_weight_per_box' => 'decimal:3',
    ];

    public function purchaseOrder(): BelongsTo { return $this->belongsTo(PurchaseOrder::class); }
    public function poItem(): BelongsTo        { return $this->belongsTo(PoItem::class); }
    public function productType(): BelongsTo   { return $this->belongsTo(ProductType::class); }
    public function creator(): BelongsTo       { return $this->belongsTo(User::class, 'created_by'); }

    public function boxes(): HasMany
    {
        return $this->hasMany(PackingBox::class)->orderBy('sequence_no');
    }

    /** Box yang batal tidak dihitung sebagai hasil. */
    public function activeBoxes(): HasMany
    {
        return $this->boxes()->where('status', '<>', 'voided');
    }

    public function getTotalNetKgAttribute(): float
    {
        return round((float) $this->activeBoxes()->sum('net_weight_kg'), 3);
    }

    public function getBoxCountAttribute(): int
    {
        return $this->activeBoxes()->count();
    }
}
