<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'contact_person', 'phone', 'email',
        'address', 'city', 'notes', 'is_active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function receivings(): HasMany
    {
        return $this->hasMany(Receiving::class, 'supplier_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Label siap pakai untuk dropdown & rekap: "NJY - UD Nelayan Jaya" */
    public function getDisplayNameAttribute(): string
    {
        return "{$this->code} - {$this->name}";
    }

    /**
     * Normalisasi kode supplier: huruf besar, hanya A-Z dan 0-9.
     * Kode ini menjadi bagian dari batch number sehingga tidak boleh
     * mengandung spasi atau tanda hubung yang bisa merusak parsing.
     */
    public static function normalizeCode(?string $code): string
    {
        return substr(preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $code)), 0, 10);
    }
}
