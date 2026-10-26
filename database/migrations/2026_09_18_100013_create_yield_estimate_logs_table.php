<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat perubahan estimasi yield.
 *
 * Estimasi yield adalah pembanding "estimasi vs aktual" di laporan produksi.
 * Karena penyimpanannya menimpa nilai lama, mengubah estimasi hari ini diam-diam
 * mengubah tafsir laporan bulan lalu. Log ini menyimpan snapshot sebelum dan
 * sesudah tiap penyimpanan supaya perubahan itu bisa ditelusuri.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yield_estimate_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_subgroup_id')->constrained()->cascadeOnDelete();
            $table->json('previous')->nullable();
            $table->json('snapshot');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['material_subgroup_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yield_estimate_logs');
    }
};
