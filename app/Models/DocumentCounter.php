<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Penyimpan nomor urut per scope. Jangan dipakai langsung;
 * gunakan App\Services\CodeGenerator yang sudah menangani penguncian.
 */
class DocumentCounter extends Model
{
    protected $fillable = ['scope', 'last_number'];

    protected $casts = ['last_number' => 'integer'];
}
