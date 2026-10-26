<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tahapan produksi (Potong -> Trimming -> Potong Steak).
 *
 * Sengaja disimpan sebagai DATA, bukan enum, supaya pabrik bisa menambah atau
 * menyisipkan tahap baru tanpa migration. `sequence` memakai kelipatan 10
 * agar tahap baru bisa diselipkan di antara dua tahap yang sudah ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_stages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();          // cutting | trimming | steak
            $table->string('short_code', 4);               // CT | TR | ST  -> komponen nomor lot
            $table->string('name', 100);                   // "Potong (Cutting)"
            $table->string('icon', 40)->default('bi-diagram-3');
            $table->string('color', 7)->default('#0d6efd');
            $table->unsignedSmallInteger('sequence');
            $table->boolean('requires_product_type')->default(false);
            $table->boolean('is_final_stage')->default(false); // output siap masuk packing
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_stages');
    }
};
