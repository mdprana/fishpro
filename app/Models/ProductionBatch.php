<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu unit fisik yang bisa dipegang orang: keranjang bahan baku, lot hasil
 * potong, lot hasil trimming, lot siap packing.
 *
 * Saldo (current_weight_kg) adalah CACHE dari batch_movements. Jangan pernah
 * menambah/mengurangi kolom itu langsung. Pakai applyMovement() supaya
 * ledger dan cache selalu sejalan.
 */
class ProductionBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_number', 'qr_code',
        'parent_batch_id', 'root_batch_id', 'level', 'is_multi_source',
        'process_stage_id', 'origin',
        'purchase_order_id', 'supplier_id', 'material_subgroup_id',
        'product_type_id', 'receiving_id', 'batch_stage_record_id',
        'content_type', 'initial_weight_kg', 'current_weight_kg',
        'container_type', 'container_count', 'status',
        'produced_at', 'effective_date', 'label_printed_at',
        'notes', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'level'             => 'integer',
        'is_multi_source'   => 'boolean',
        'initial_weight_kg' => 'decimal:3',
        'current_weight_kg' => 'decimal:3',
        'container_count'   => 'integer',
        'produced_at'       => 'datetime',
        'effective_date'    => 'date',
        'label_printed_at'  => 'datetime',
    ];

    /** Toleransi pembulatan timbangan (setara 1 gram). */
    public const EPSILON = 0.001;

    // ── Relasi ────────────────────────────────────────────────────

    public function parent(): BelongsTo        { return $this->belongsTo(self::class, 'parent_batch_id'); }
    public function children(): HasMany        { return $this->hasMany(self::class, 'parent_batch_id'); }
    public function rootBatch(): BelongsTo     { return $this->belongsTo(self::class, 'root_batch_id'); }
    public function supplier(): BelongsTo      { return $this->belongsTo(Supplier::class); }
    public function purchaseOrder(): BelongsTo { return $this->belongsTo(PurchaseOrder::class); }
    public function subgroup(): BelongsTo      { return $this->belongsTo(MaterialSubgroup::class, 'material_subgroup_id'); }
    public function productType(): BelongsTo   { return $this->belongsTo(ProductType::class); }
    public function stage(): BelongsTo         { return $this->belongsTo(ProcessStage::class, 'process_stage_id'); }
    public function receiving(): BelongsTo     { return $this->belongsTo(Receiving::class); }
    public function creator(): BelongsTo       { return $this->belongsTo(User::class, 'created_by'); }
    public function stageRecord(): BelongsTo   { return $this->belongsTo(BatchStageRecord::class, 'batch_stage_record_id'); }

    public function movements(): HasMany
    {
        return $this->hasMany(BatchMovement::class, 'batch_id')->orderBy('id');
    }

    /** Dari batch mana saja isi lot ini berasal. */
    public function sourceLinks(): HasMany
    {
        return $this->hasMany(BatchStageLink::class, 'output_batch_id');
    }

    /** Ke lot mana saja isi batch ini mengalir. */
    public function outputLinks(): HasMany
    {
        return $this->hasMany(BatchStageLink::class, 'source_batch_id');
    }

    public function packingSources(): HasMany
    {
        return $this->hasMany(PackingBoxSource::class, 'batch_id');
    }

    // ── Scope ─────────────────────────────────────────────────────

    /** Batch yang masih punya isi dan belum dibatalkan. */
    public function scopeAvailable($query)
    {
        return $query->whereIn('status', ['available', 'partial'])
            ->where('current_weight_kg', '>', self::EPSILON);
    }

    public function scopeNotVoided($query)
    {
        return $query->where('status', '<>', 'voided');
    }

    /** Batch induk hasil penerimaan. */
    public function scopeRoots($query)
    {
        return $query->where('level', 0);
    }

    // ── Accessor ──────────────────────────────────────────────────

    /** Ekor nomor batch untuk tabel sempit; nomor penuh tetap ditaruh di tooltip. */
    public function getShortCodeAttribute(): string
    {
        return strlen($this->batch_number) <= 16
            ? $this->batch_number
            : '...' . substr($this->batch_number, -15);
    }

    public function getIsAvailableAttribute(): bool
    {
        return $this->status !== 'voided' && (float) $this->current_weight_kg > self::EPSILON;
    }

    public function getConsumedKgAttribute(): float
    {
        return round((float) $this->initial_weight_kg - (float) $this->current_weight_kg, 3);
    }

    public function getStageLabelAttribute(): string
    {
        return $this->stage?->name ?? 'Bahan Baku (Bongkar)';
    }

    /** Isi QR: URL absolut supaya kamera HP bawaan pun langsung membuka halaman telusur. */
    public function getQrUrlAttribute(): string
    {
        return route('trace.batch', $this->qr_code);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'available' => 'Tersedia',
            'partial'   => 'Terpakai sebagian',
            'consumed'  => 'Habis terpakai',
            'packed'    => 'Sudah dipacking',
            'voided'    => 'Dibatalkan',
            default     => $this->status,
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'available' => 'success',
            'partial'   => 'warning',
            'consumed'  => 'secondary',
            'packed'    => 'primary',
            'voided'    => 'danger',
            default     => 'secondary',
        };
    }

    // ── Perilaku ledger ───────────────────────────────────────────

    /**
     * Catat satu pergerakan berat lalu perbarui cache saldo.
     *
     * Panggil selalu di dalam DB::transaction dan setelah baris batch dikunci
     * (lockForUpdate), supaya dua operator tidak bisa menghabiskan batch yang sama.
     */
    public function applyMovement(string $type, string $direction, float $weightKg, array $context = []): BatchMovement
    {
        if ($weightKg <= 0) {
            throw new \InvalidArgumentException('Berat pergerakan harus lebih besar dari nol.');
        }

        $delta   = $direction === 'in' ? $weightKg : -$weightKg;
        $balance = round((float) $this->current_weight_kg + $delta, 3);

        if ($balance < -self::EPSILON) {
            throw new \RuntimeException(
                "Batch {$this->batch_number} hanya tersisa {$this->current_weight_kg} kg, "
                . "tidak cukup untuk mengambil {$weightKg} kg."
            );
        }

        $balance = max(0, $balance); // bersihkan galat pembulatan timbangan

        $movement = $this->movements()->create([
            'movement_type'         => $type,
            'direction'             => $direction,
            'weight_kg'             => $weightKg,
            'balance_after'         => $balance,
            'batch_stage_record_id' => $context['batch_stage_record_id'] ?? null,
            'packing_box_id'        => $context['packing_box_id'] ?? null,
            'receiving_id'          => $context['receiving_id'] ?? null,
            'reversal_of_id'        => $context['reversal_of_id'] ?? null,
            'occurred_at'           => $context['occurred_at'] ?? now(),
            'note'                  => $context['note'] ?? null,
            'created_by'            => $context['created_by'] ?? auth()->id(),
        ]);

        $this->current_weight_kg = $balance;
        $this->status            = $this->resolveStatus($balance);
        $this->save();

        return $movement;
    }

    /**
     * Hitung ulang saldo dari ledger. Dipakai perintah audit `batch:verify`
     * untuk membuktikan cache tidak melenceng dari sumber kebenarannya.
     */
    public function recalcFromLedger(): float
    {
        $in  = (float) $this->movements()->where('direction', 'in')->sum('weight_kg');
        $out = (float) $this->movements()->where('direction', 'out')->sum('weight_kg');

        $balance = round($in - $out, 3);

        $this->current_weight_kg = $balance;
        $this->status            = $this->resolveStatus($balance);
        $this->save();

        return $balance;
    }

    // ── Penelusuran silsilah ──────────────────────────────────────

    /**
     * Uraikan lot ini menjadi jalur-jalur asal sampai ke batch induk penerimaan.
     *
     * Satu jalur = satu batch induk beserta tanggal tiap tahap yang dilaluinya.
     * Berat dibagi mengikuti porsi kontribusi di batch_stage_links, sehingga
     * sebuah box 5 kg yang berisi campuran bisa menyebut "3,2 kg dari batch A,
     * 1,8 kg dari batch B".
     *
     * Silsilah ditelusuri lewat batch_stage_links, BUKAN parent_batch_id.
     * Parent hanya penanda induk dominan untuk penomoran dan tampilan.
     *
     * @return \Illuminate\Support\Collection<int, array{root: self, kg: float, stages: array<string, self>}>
     */
    public function tracePaths(?float $weight = null, int $depth = 0): \Illuminate\Support\Collection
    {
        $weight ??= (float) $this->initial_weight_kg;

        // Jaring pengaman: data yang rusak tidak boleh membuat rekursi tak berujung.
        if ($depth > 20) {
            return collect();
        }

        $links = $this->sourceLinks()->with('sourceBatch')->get();

        if ($links->isEmpty()) {
            // Tidak punya asal lagi. Inilah batch induk dari penerimaan.
            return collect([['root' => $this, 'kg' => round($weight, 3), 'stages' => []]]);
        }

        $total = (float) $links->sum('contributed_kg');
        $paths = collect();

        foreach ($links as $link) {
            $share = $total > 0 ? $weight * ((float) $link->contributed_kg / $total) : 0.0;

            foreach ($link->sourceBatch->tracePaths($share, $depth + 1) as $path) {
                $paths->push($path);
            }
        }

        $stageCode = $this->stage?->code;

        // Sisipkan tahap milik lot ini ke setiap jalur yang melewatinya.
        return $paths->map(function (array $path) use ($stageCode) {
            if ($stageCode) {
                $path['stages'][$stageCode] = $this;
            }
            return $path;
        });
    }

    /**
     * Jalur asal yang sudah digabung per batch induk, agar satu induk tidak
     * muncul beberapa kali ketika isinya sempat terpecah lalu bertemu lagi.
     *
     * @return \Illuminate\Support\Collection<int, array{root: self, kg: float, pct: float, stages: array<string, self>}>
     */
    public function rootLots(?float $weight = null): \Illuminate\Support\Collection
    {
        $weight ??= (float) $this->initial_weight_kg;

        return $this->tracePaths($weight)
            ->groupBy(fn (array $path) => $path['root']->id)
            ->map(function ($group) use ($weight) {
                $kg = round($group->sum('kg'), 3);

                return [
                    'root'   => $group->first()['root'],
                    'kg'     => $kg,
                    'pct'    => $weight > 0 ? round(($kg / $weight) * 100, 2) : 0.0,
                    // Tahap dari jalur mana pun sama; ambil yang pertama.
                    'stages' => $group->first()['stages'],
                ];
            })
            ->sortByDesc('kg')
            ->values();
    }

    private function resolveStatus(float $balance): string
    {
        if ($this->status === 'voided') {
            return 'voided';
        }

        if ($balance <= self::EPSILON) {
            return $this->status === 'packed' ? 'packed' : 'consumed';
        }

        return $balance < (float) $this->initial_weight_kg - self::EPSILON ? 'partial' : 'available';
    }
}
