<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Standar toleransi penyusutan pabrik, per jenis bahan per tahap.
 * Contoh: YF LOIN EROPA di tahap Potong minimal 70%, Trimming 85%, Steak 92%.
 *
 * VERSIONED: mengubah standar berarti menutup baris lama (effective_to) dan
 * menyisipkan baris baru. Tanpa ini, menaikkan standar hari ini akan membuat
 * hasil produksi bulan lalu ikut dinilai "di bawah standar" secara surut.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yield_standards', function (Blueprint $table) {
            $table->id();
            // null = berlaku umum untuk semua bahan
            $table->foreignId('material_subgroup_id')->nullable()->constrained()->cascadeOnDelete();
            // null = standar recovery keseluruhan PO, bukan per tahap
            $table->foreignId('process_stage_id')->nullable()->constrained();
            $table->foreignId('product_type_id')->nullable()->constrained();

            $table->enum('scope', ['stage', 'overall'])->default('stage');
            $table->decimal('min_yield_pct', 5, 2);                 // di bawah ini = alert merah
            $table->decimal('warn_yield_pct', 5, 2)->nullable();    // ambang kuning
            $table->decimal('target_yield_pct', 5, 2)->nullable();  // sasaran ideal

            $table->date('effective_from');
            $table->date('effective_to')->nullable();               // null = masih berlaku
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['material_subgroup_id', 'process_stage_id', 'is_active'], 'idx_std_lookup');
            $table->index(['effective_from', 'effective_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yield_standards');
    }
};
