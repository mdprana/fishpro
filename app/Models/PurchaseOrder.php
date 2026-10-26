<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'po_number','buyer_name','order_date','deadline','status','notes','created_by',
    ];

    protected $casts = [
        'order_date' => 'date',
        'deadline'   => 'date',
    ];

    // ── Relationships ──────────────────────────────────────────
    public function items(): HasMany
    {
        return $this->hasMany(PoItem::class, 'purchase_order_id');
    }

    public function receivings(): HasMany
    {
        return $this->hasMany(Receiving::class, 'purchase_order_id');
    }

    public function packingBoxes(): HasMany
    {
        return $this->hasMany(PackingBox::class, 'purchase_order_id');
    }

    public function packingRuns(): HasMany
    {
        return $this->hasMany(PackingRun::class, 'purchase_order_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ProductionBatch::class, 'purchase_order_id');
    }

    public function stageRecords(): HasMany
    {
        return $this->hasMany(BatchStageRecord::class, 'purchase_order_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Summary Metrics ────────────────────────────────────────

    /** Total target berat semua produk di PO ini */
    public function getTotalTargetWeightAttribute(): float
    {
        return (float) $this->items()->sum('target_weight_kg');
    }

    /** Total target box semua produk */
    public function getTotalTargetBoxAttribute(): int
    {
        return (int) $this->items()->sum('target_box');
    }

    /** Total hasil produksi (utama + sampingan) dari seluruh tahap. */
    public function getTotalOutputKgAttribute(): float
    {
        return (float) BatchStageOutput::whereIn(
            'batch_stage_record_id',
            $this->stageRecords()->where('status', 'completed')->pluck('id')
        )->whereIn('output_type', ['utama', 'sampingan'])->sum('output_weight_kg');
    }

    /** Total sudah di-pack (Kg), dijumlah dari berat box fisik. */
    public function getTotalPackedKgAttribute(): float
    {
        return (float) $this->packingBoxes()->active()->sum('net_weight_kg');
    }

    /** Total box sudah di-pack */
    public function getTotalPackedBoxAttribute(): int
    {
        return $this->packingBoxes()->active()->count();
    }

    /** Total bahan baku yang diterima untuk PO ini. */
    public function getTotalReceivedKgAttribute(): float
    {
        return (float) $this->receivings()->sum('weight_kg');
    }

    /**
     * Recovery = Total Berat Packing / Total Berat Bahan Baku x 100%.
     * Inilah angka kunci yang dipantau pabrik.
     */
    public function getRecoveryPctAttribute(): float
    {
        $bahan = $this->total_received_kg;

        return $bahan > 0 ? round(($this->total_packed_kg / $bahan) * 100, 2) : 0.0;
    }

    /** Progress packing keseluruhan % */
    public function getPackingProgressAttribute(): float
    {
        $target = $this->total_target_box;
        if ($target <= 0) return 0;
        return round(($this->total_packed_box / $target) * 100, 1);
    }

    /** Apakah PO sudah selesai (semua box terpenuhi)? */
    public function isCompleted(): bool
    {
        return $this->total_packed_box >= $this->total_target_box && $this->total_target_box > 0;
    }

    /** Hari tersisa hingga deadline */
    public function getDaysRemainingAttribute(): int
    {
        return max(0, $this->days_remaining_raw);
    }

    public function getDaysRemainingRawAttribute(): int
    {
        return (int) today()->diffInDays($this->deadline->copy()->startOfDay(), false);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['active','in_progress']);
    }

    /**
     * Sertakan tanggal pertama & terakhir tiap tahap sebagai kolom hasil query.
     *
     * Dipakai daftar PO supaya kolom tanggal bongkar/potong/trimming/steak/packing
     * tidak memicu query tambahan per baris.
     */
    public function scopeWithStageDates($query)
    {
        $query->withMin('receivings as first_receiving_date', 'received_date')
              ->withMax('receivings as last_receiving_date', 'received_date')
              ->withMin('packingBoxes as first_packing_date', 'packing_date')
              ->withMax('packingBoxes as last_packing_date', 'packing_date');

        foreach (ProcessStage::active()->ordered()->get() as $stage) {
            $filter = fn ($q) => $q->where('process_stage_id', $stage->id)->where('status', 'completed');

            $query->withMin(["stageRecords as first_{$stage->code}_date" => $filter], 'process_date')
                  ->withMax(["stageRecords as last_{$stage->code}_date"  => $filter], 'process_date');
        }

        return $query;
    }

    /**
     * Timeline lengkap PO untuk kartu di halaman detail.
     *
     * @return array<int, array<string, mixed>>
     */
    public function stageTimeline(): array
    {
        $timeline = [[
            'code'  => 'receiving',
            'name'  => 'Bongkar',
            'icon'  => 'bi-box-arrow-in-down',
            'color' => '#0ea5e9',
            'first' => $this->receivings()->min('received_date'),
            'last'  => $this->receivings()->max('received_date'),
            'count' => $this->receivings()->count(),
            'kg'    => round((float) $this->receivings()->sum('weight_kg'), 2),
            'yield' => null,
        ]];

        foreach (ProcessStage::active()->ordered()->get() as $stage) {
            $records = $this->stageRecords()->where('process_stage_id', $stage->id)->where('status', 'completed');

            $input = (float) (clone $records)->sum('total_input_kg');
            $utama = (float) BatchStageOutput::whereIn('batch_stage_record_id', (clone $records)->pluck('id'))
                ->where('output_type', 'utama')->sum('output_weight_kg');

            $timeline[] = [
                'code'  => $stage->code,
                'name'  => $stage->name,
                'icon'  => $stage->icon,
                'color' => $stage->color,
                'first' => (clone $records)->min('process_date'),
                'last'  => (clone $records)->max('process_date'),
                'count' => (clone $records)->count(),
                'kg'    => round($utama, 2),
                // Yield tahap diukur dari hasil utama terhadap bahan yang masuk.
                'yield' => $input > 0 ? round(($utama / $input) * 100, 1) : null,
            ];
        }

        $boxes = $this->packingBoxes()->active();

        $timeline[] = [
            'code'  => 'packing',
            'name'  => 'Packing',
            'icon'  => 'bi-box-seam',
            'color' => '#16a34a',
            'first' => (clone $boxes)->min('packing_date'),
            'last'  => (clone $boxes)->max('packing_date'),
            'count' => (clone $boxes)->count(),
            'kg'    => round((float) (clone $boxes)->sum('net_weight_kg'), 2),
            'yield' => null,
        ];

        return $timeline;
    }
}
