<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receiving extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'material_subgroup_id','purchase_order_id','supplier_id','batch_code',
        'weight_kg','container_type','container_count','split_per_container',
        'doc_number','vehicle_number','received_date','unload_start','unload_end',
        'temperature_c','notes','created_by','updated_by',
    ];

    protected $casts = [
        'weight_kg'           => 'decimal:2',
        'received_date'       => 'date',
        'split_per_container' => 'boolean',
        'container_count'     => 'integer',
        'temperature_c'       => 'decimal:2',
    ];

    /*
     * CATATAN: boot() yang dulu menambah/mengembalikan stok DIHAPUS.
     *
     * Hook `created` menambah stok DAN ReceivingController@store menambah stok lagi
     * -> terima 100 kg, stok naik 200 kg.
     * Hook `deleting` mengembalikan stok DAN controller menguranginya
     * -> hapus penerimaan, stok tidak berubah sama sekali.
     *
     * Mutasi stok sekarang menjadi tanggung jawab satu pihak saja (controller,
     * dan nanti StockService), sehingga tidak ada lagi jalur ganda.
     */

    public function subgroup(): BelongsTo
    {
        return $this->belongsTo(MaterialSubgroup::class, 'material_subgroup_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    /** Label supplier siap tampil: "NJY - UD Nelayan Jaya". */
    public function getSupplierLabelAttribute(): string
    {
        return $this->supplier?->display_name ?? '-';
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Estimasi total output dari penerimaan ini berdasarkan yield_estimates
     * Returns collection ['product_type'=>..,'percentage'=>..,'estimated_kg'=>..]
     */
    public function getEstimatedOutputsAttribute()
    {
        $weight = (float)$this->weight_kg;
        return $this->subgroup->yieldEstimates()->with('productType')->get()->map(function ($ye) use ($weight) {
            return [
                'product_type' => $ye->productType,
                'percentage'   => (float)$ye->percentage,
                'estimated_kg' => round($weight * (float)$ye->percentage / 100, 2),
            ];
        });
    }

    /** Total estimasi yield % (sum semua produk) */
    public function getTotalYieldPctAttribute(): float
    {
        return (float)$this->subgroup->total_yield_pct;
    }

    /** Total estimasi output (kg) dari penerimaan ini */
    public function getEstimatedOutputKgAttribute(): float
    {
        return round((float)$this->weight_kg * $this->total_yield_pct / 100, 2);
    }
}
