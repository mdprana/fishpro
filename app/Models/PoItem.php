<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PoItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'purchase_order_id','product_type_id',
        'target_weight_kg','target_box','weight_per_box_kg','notes',
    ];

    protected $casts = [
        'target_weight_kg'  => 'decimal:2',
        'target_box'        => 'integer',
        'weight_per_box_kg' => 'decimal:2',
    ];

    /**
     * Berat per box:
     * - Jika diisi manual → pakai nilai itu
     * - Jika kosong & target_box > 0 → hitung dari target_weight / target_box
     */
    public function getEffectiveWeightPerBoxAttribute(): float
    {
        if ($this->weight_per_box_kg > 0) return (float)$this->weight_per_box_kg;
        if ((int)$this->target_box > 0) return round((float)$this->target_weight_kg / (int)$this->target_box, 2);
        return 0;
    }

    /**
     * Jika berat/box diisi dan target_box kosong → hitung box dari berat
     * Jika target_box diisi dan berat/box kosong → hitung berat/box
     */
    public static function resolveBoxWeight(array $data): array
    {
        $weight = (float)($data['target_weight_kg'] ?? 0);
        $box    = (int)($data['target_box'] ?? 0);
        $wpb    = (float)($data['weight_per_box_kg'] ?? 0);

        if ($weight > 0 && $box > 0 && $wpb == 0) {
            $data['weight_per_box_kg'] = round($weight / $box, 2);
        } elseif ($weight > 0 && $wpb > 0 && $box == 0) {
            $data['target_box'] = (int) ceil($weight / $wpb);
        } elseif ($box > 0 && $wpb > 0 && $weight == 0) {
            $data['target_weight_kg'] = round($box * $wpb, 2);
        }
        return $data;
    }

    // Sudah di-pack. Dihitung dari box fisik, bukan dari angka box_count,
    // sehingga berat yang berbeda-beda antar box ikut terhitung benar.
    public function getPackedKgAttribute(): float
    {
        return (float) $this->packingBoxes()->active()->sum('net_weight_kg');
    }
    public function getPackedBoxAttribute(): int
    {
        return $this->packingBoxes()->active()->count();
    }
    public function getRemainingBoxAttribute(): int
    {
        return max(0, $this->target_box - $this->packed_box);
    }
    public function getProgressPctAttribute(): float
    {
        if ($this->target_box <= 0) return 0;
        return round(($this->packed_box / $this->target_box) * 100, 1);
    }

    public function purchaseOrder(): BelongsTo { return $this->belongsTo(PurchaseOrder::class,'purchase_order_id'); }
    public function productType(): BelongsTo   { return $this->belongsTo(ProductType::class,'product_type_id'); }
    public function packingBoxes(): HasMany     { return $this->hasMany(PackingBox::class,'po_item_id'); }
}
