<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RawMaterialStock extends Model
{
    protected $fillable = ['material_subgroup_id','current_stock_kg'];
    protected $casts    = ['current_stock_kg' => 'decimal:2'];

    public function subgroup(): BelongsTo
    {
        return $this->belongsTo(MaterialSubgroup::class, 'material_subgroup_id');
    }

    public function addStock(float $weightKg): void
    {
        $this->current_stock_kg += $weightKg;
        $this->save();
    }

    /**
     * Kurangi stok. Sengaja TIDAK memakai max(0, ...) karena menelan selisih minus
     * diam-diam membuat angka stok salah tanpa jejak. Lebih baik gagal keras
     * supaya pemanggil (yang sudah lockForUpdate) menangani kekurangan stok.
     */
    public function deductStock(float $weightKg): void
    {
        $sisa = (float) $this->current_stock_kg - $weightKg;

        if ($sisa < -0.001) {
            throw new \RuntimeException(
                "Stok {$this->subgroup?->name} tidak cukup: tersedia {$this->current_stock_kg} kg, diminta {$weightKg} kg."
            );
        }

        $this->current_stock_kg = max(0, $sisa); // hanya membersihkan galat pembulatan
        $this->save();
    }

    public function returnStock(float $weightKg): void
    {
        $this->current_stock_kg += $weightKg;
        $this->save();
    }
}
