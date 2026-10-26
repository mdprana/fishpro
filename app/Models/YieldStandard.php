<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class YieldStandard extends Model
{
    protected $fillable = [
        'material_subgroup_id', 'process_stage_id', 'product_type_id', 'scope',
        'min_yield_pct', 'warn_yield_pct', 'target_yield_pct',
        'effective_from', 'effective_to', 'is_active', 'notes', 'created_by',
    ];

    protected $casts = [
        'min_yield_pct'    => 'decimal:2',
        'warn_yield_pct'   => 'decimal:2',
        'target_yield_pct' => 'decimal:2',
        'effective_from'   => 'date',
        'effective_to'     => 'date',
        'is_active'        => 'boolean',
    ];

    public function subgroup(): BelongsTo    { return $this->belongsTo(MaterialSubgroup::class, 'material_subgroup_id'); }
    public function stage(): BelongsTo       { return $this->belongsTo(ProcessStage::class, 'process_stage_id'); }
    public function productType(): BelongsTo { return $this->belongsTo(ProductType::class); }
    public function creator(): BelongsTo     { return $this->belongsTo(User::class, 'created_by'); }

    /** Standar yang berlaku pada tanggal tertentu. */
    public function scopeEffectiveOn($query, Carbon|string $date)
    {
        $date = $date instanceof Carbon ? $date : Carbon::parse($date);

        return $query->where('is_active', true)
            ->whereDate('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date));
    }

    /**
     * Cari standar paling spesifik yang berlaku: aturan khusus subgroup
     * mengalahkan aturan umum (material_subgroup_id null).
     *
     * Dinilai memakai tanggal produksi, bukan hari ini, supaya laporan lama
     * tetap dinilai dengan standar yang berlaku saat itu.
     */
    public static function resolve(
        ?int $subgroupId,
        ?int $stageId,
        Carbon|string $date,
        string $scope = 'stage'
    ): ?self {
        return static::query()
            ->effectiveOn($date)
            ->where('scope', $scope)
            ->where(fn ($q) => $q->where('process_stage_id', $stageId)->orWhereNull('process_stage_id'))
            ->where(fn ($q) => $q->where('material_subgroup_id', $subgroupId)->orWhereNull('material_subgroup_id'))
            // Yang paling spesifik menang.
            ->orderByRaw('material_subgroup_id IS NULL')
            ->orderByRaw('process_stage_id IS NULL')
            ->orderByDesc('effective_from')
            ->first();
    }

    /**
     * Nilai sebuah yield terhadap standar ini.
     *
     * @return array{level: 'ok'|'warn'|'below', message: ?string}
     */
    public function evaluate(float $yieldPct): array
    {
        $min  = (float) $this->min_yield_pct;
        $warn = $this->warn_yield_pct !== null ? (float) $this->warn_yield_pct : null;

        if ($yieldPct < $min) {
            return [
                'level'   => 'below',
                'message' => sprintf(
                    'Penyusutan melebihi toleransi pabrik (standar minimal %s%%, aktual %s%%).',
                    rtrim(rtrim(number_format($min, 2, ',', '.'), '0'), ','),
                    rtrim(rtrim(number_format($yieldPct, 2, ',', '.'), '0'), ',')
                ),
            ];
        }

        if ($warn !== null && $yieldPct < $warn) {
            return [
                'level'   => 'warn',
                'message' => sprintf(
                    'Yield di bawah ambang perhatian (%s%%), masih di atas batas minimal.',
                    rtrim(rtrim(number_format($warn, 2, ',', '.'), '0'), ',')
                ),
            ];
        }

        return ['level' => 'ok', 'message' => null];
    }
}
