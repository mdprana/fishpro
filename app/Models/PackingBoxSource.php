<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Komposisi isi sebuah box: berapa kg dari batch mana.
 *
 * root_batch_id & supplier_id didenormalisasi agar rekap lintas ratusan box
 * bisa dikerjakan satu query GROUP BY, bukan rekursi silsilah per box.
 */
class PackingBoxSource extends Model
{
    protected $fillable = [
        'packing_box_id', 'batch_id', 'root_batch_id', 'supplier_id',
        'contributed_kg', 'contribution_pct',
    ];

    protected $casts = [
        'contributed_kg'   => 'decimal:3',
        'contribution_pct' => 'decimal:3',
    ];

    public function box(): BelongsTo       { return $this->belongsTo(PackingBox::class, 'packing_box_id'); }
    public function batch(): BelongsTo     { return $this->belongsTo(ProductionBatch::class, 'batch_id'); }
    public function rootBatch(): BelongsTo { return $this->belongsTo(ProductionBatch::class, 'root_batch_id'); }
    public function supplier(): BelongsTo  { return $this->belongsTo(Supplier::class); }
}
