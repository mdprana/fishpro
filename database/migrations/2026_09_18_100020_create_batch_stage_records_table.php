<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catatan satu sesi kerja pada sebuah tahap (Potong / Trimming / Potong Steak).
 * Menggantikan `production_sessions` yang jenis prosesnya hanya teks bebas
 * dan tidak bisa merangkai output tahap N menjadi input tahap N+1.
 *
 * Kolom total_* dan yield_pct adalah cache dari baris input/output/waste,
 * disimpan agar laporan dan alert tidak perlu menghitung ulang tiap kali.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_stage_records', function (Blueprint $table) {
            $table->id();
            $table->string('record_code', 60)->unique();      // CUT-260918-0007
            $table->foreignId('process_stage_id')->constrained('process_stages');
            $table->foreignId('purchase_order_id')->constrained();

            $table->date('process_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('operator_name', 200)->nullable();
            $table->string('shift', 20)->nullable();
            $table->string('line_name', 50)->nullable();

            $table->decimal('total_input_kg', 12, 3)->default(0);
            $table->decimal('total_output_kg', 12, 3)->default(0);  // utama + sampingan
            $table->decimal('total_waste_kg', 12, 3)->default(0);
            $table->decimal('yield_pct', 6, 2)->default(0);         // output utama / input

            // Pembatalan memakai status + reversal ledger, bukan softDeletes,
            // supaya catatan yang salah tetap terlihat saat audit.
            $table->enum('status', ['draft', 'completed', 'voided'])->default('completed');
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Nama indeks dipendekkan manual: gabungan ketiga kolom melebihi
            // batas 64 karakter untuk identifier MySQL.
            $table->index(['purchase_order_id', 'process_stage_id', 'process_date'], 'idx_stage_rec_po_stage_date');
            $table->index('process_date');
        });

        // FK yang sengaja ditunda karena referensinya melingkar:
        // production_batches lahir dari sebuah stage record, dan stage record
        // menunjuk batch lewat tabel input/output.
        Schema::table('production_batches', function (Blueprint $table) {
            $table->foreign('batch_stage_record_id')
                ->references('id')->on('batch_stage_records')->nullOnDelete();
        });

        Schema::table('batch_movements', function (Blueprint $table) {
            $table->foreign('batch_stage_record_id')
                ->references('id')->on('batch_stage_records')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('batch_movements', fn (Blueprint $t) => $t->dropForeign(['batch_stage_record_id']));
        Schema::table('production_batches', fn (Blueprint $t) => $t->dropForeign(['batch_stage_record_id']));
        Schema::dropIfExists('batch_stage_records');
    }
};
