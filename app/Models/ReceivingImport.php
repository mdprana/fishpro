<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReceivingImport extends Model
{
    public $timestamps = false;

    protected $fillable = ['actor_id', 'nonce', 'content_hash', 'row_count', 'receiving_ids'];

    protected $casts = ['actor_id' => 'integer', 'row_count' => 'integer', 'receiving_ids' => 'array', 'created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Tanda terima impor tidak dapat diubah.'));
        static::deleting(fn () => throw new \LogicException('Tanda terima impor tidak dapat dihapus.'));
    }
}
