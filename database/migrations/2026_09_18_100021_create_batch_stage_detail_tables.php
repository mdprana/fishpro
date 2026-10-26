<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rincian satu sesi tahap: apa yang masuk, apa yang keluar, dan berapa kg
 * dari tiap batch masukan menyumbang ke tiap batch keluaran.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Batch yang dipakai sebagai input tahap ini.
        Schema::create('batch_stage_inputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_stage_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained('production_batches');
            $table->decimal('input_weight_kg', 12, 3);
            $table->text('notes')->nullable();
            $table->timestamps();

            // Satu batch cukup satu baris per sesi; ambil dua kali digabung saja.
            $table->unique(['batch_stage_record_id', 'batch_id'], 'uniq_stage_input');
            $table->index('batch_id');
        });

        // Hasil tahap: produk utama, sampingan, dan limbah.
        Schema::create('batch_stage_outputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_stage_record_id')->constrained()->cascadeOnDelete();
            // null untuk waste, sebab limbah tidak menjadi batch yang bisa diproses lagi.
            $table->foreignId('output_batch_id')->nullable()->constrained('production_batches')->nullOnDelete();
            $table->enum('output_type', ['utama', 'sampingan', 'waste']);
            // kepala, isi perut, kulit, duri, darah, serutan, …
            $table->string('waste_category', 60)->nullable();
            $table->foreignId('product_type_id')->nullable()->constrained('product_types');
            $table->decimal('output_weight_kg', 12, 3);
            $table->string('container_type', 100)->nullable();
            $table->unsignedInteger('container_count')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['batch_stage_record_id', 'output_type']);
        });

        /**
         * Jembatan input -> output. Tanpa tabel ini, sesi yang menggabungkan
         * beberapa batch tidak bisa ditelusuri balik: kita hanya tahu "700 kg
         * keluar", bukan "140 kg dari batch A, 210 kg dari B, 350 kg dari C".
         * Inilah yang membuat satu box packing bisa menyebut semua asal-usulnya.
         */
        Schema::create('batch_stage_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_stage_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_stage_output_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_batch_id')->constrained('production_batches');
            $table->foreignId('output_batch_id')->nullable()->constrained('production_batches')->nullOnDelete();
            $table->decimal('contributed_kg', 12, 3);
            $table->decimal('contribution_pct', 6, 3)->default(0);
            $table->enum('allocation_method', ['proportional', 'manual'])->default('proportional');
            $table->timestamps();

            $table->index('output_batch_id');
            $table->index('source_batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_stage_links');
        Schema::dropIfExists('batch_stage_outputs');
        Schema::dropIfExists('batch_stage_inputs');
    }
};
