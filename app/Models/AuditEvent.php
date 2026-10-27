<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Satu jejak aksi bisnis. Append-only: model menolak update/delete, dan
 * trigger DB menolak juga lewat query builder. Ditulis hanya oleh AuditService.
 */
class AuditEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_id', 'recorded_at', 'entity_type', 'entity_id', 'action', 'movement_id', 'before', 'after',
    ];

    protected $casts = [
        'before' => 'array',
        'after'  => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('audit_events bersifat append-only.'));
        static::deleting(fn () => throw new \LogicException('audit_events bersifat append-only.'));
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** recorded_at disimpan UTC; ditampilkan di zona waktu aplikasi. */
    public function getRecordedLocalAttribute(): Carbon
    {
        return Carbon::createFromFormat('Y-m-d H:i:s', $this->getRawOriginal('recorded_at'), 'UTC')
            ->setTimezone(config('app.timezone'));
    }

    /** @return array<string, string> label => teks siap tampil (di-escape oleh Blade) */
    public static function present(?array $snapshot): array
    {
        $out = [];

        foreach ($snapshot ?? [] as $key => $value) {
            $out[$key] = match (true) {
                $value === null  => '-',
                is_bool($value)  => $value ? 'Ya' : 'Tidak',
                $key === 'items' => collect($value)->map(fn ($i) => "produk #{$i['product_type_id']}: {$i['target_weight_kg']} kg / {$i['target_box']} box")->implode('; ') ?: '-',
                str_ends_with($key, '_kg') => "{$value} kg",
                default          => (string) $value,
            };
        }

        return $out;
    }
}
