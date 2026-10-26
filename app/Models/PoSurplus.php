<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PoSurplus extends Model
{
    protected $fillable = [
        'surplus_code','source_po_id','product_type_id',
        'surplus_type','weight_kg','box_count','status','notes','created_by',
    ];

    protected $casts = [
        'weight_kg' => 'decimal:2',
    ];

    public function sourcePO(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'source_po_id');
    }

    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class, 'product_type_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Generate kode surplus otomatis */
    public static function generateCode(string $poNumber): string
    {
        $clean = strtoupper(str_replace(['PO-','-','/',' '], '', $poNumber));
        $count = self::where('surplus_code', 'like', "SRS-{$clean}-%")->count();
        return sprintf('SRS-%s-%03d', $clean, $count + 1);
    }

    public function getTypeLabel(): string
    {
        return match($this->surplus_type) {
            'process' => '⚙️ Sisa Proses',
            'packing' => '📦 Sisa Packing',
            default   => $this->surplus_type,
        };
    }

    public function getStatusLabel(): string
    {
        return match($this->status) {
            'available'  => 'Tersedia',
            'allocated'  => 'Dialokasikan',
            'used'       => 'Terpakai',
            default      => $this->status,
        };
    }
}
