<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LEDGER append-only. Ini sumber kebenaran saldo setiap batch;
 * `production_batches.current_weight_kg` hanyalah cache dari tabel ini.
 *
 * Tidak pernah di-UPDATE atau di-DELETE. Koreksi ditulis sebagai baris baru
 * bertipe `reversal` yang menunjuk baris yang dibatalkan lewat reversal_of_id,
 * sehingga riwayat lama tetap bisa dibaca saat audit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('production_batches')->cascadeOnDelete();

            $table->enum('movement_type', [
                'receipt',        // + dari penerimaan bahan baku
                'stage_consume',  // - dipakai sebuah tahap
                'stage_produce',  // + dihasilkan sebuah tahap
                'waste',          // - limbah (tidak menjadi batch baru)
                'pack',           // - masuk ke box packing
                'adjustment',     // +/- koreksi timbangan
                'reversal',       // pembatalan baris lain
            ]);
            $table->enum('direction', ['in', 'out']);
            $table->decimal('weight_kg', 12, 3);      // selalu positif; arah ada di `direction`
            $table->decimal('balance_after', 12, 3);  // snapshot saldo batch setelah baris ini

            // FK menyusul setelah tabelnya ada (stage record & packing box).
            $table->unsignedBigInteger('batch_stage_record_id')->nullable();
            $table->unsignedBigInteger('packing_box_id')->nullable();
            $table->foreignId('receiving_id')->nullable()->constrained('receivings')->nullOnDelete();
            $table->foreignId('reversal_of_id')->nullable()->constrained('batch_movements')->nullOnDelete();

            $table->dateTime('occurred_at');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            // Sengaja hanya created_at: tidak ada updated_at karena baris ini
            // memang tidak pernah berubah setelah ditulis.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['batch_id', 'id']);
            $table->index(['movement_type', 'occurred_at']);
            $table->index('batch_stage_record_id');
            $table->index('packing_box_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_movements');
    }
};
