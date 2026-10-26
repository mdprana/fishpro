<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul produksi lama dilepas.
 *
 * `production_sessions` menyimpan jenis proses sebagai teks bebas dan tidak
 * bisa merangkai hasil satu tahap menjadi bahan tahap berikutnya, dua hal
 * yang menjadi syarat penelusuran. Digantikan batch_stage_records beserta
 * tabel input/output/link-nya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('production_outputs');
        Schema::dropIfExists('production_inputs');
        Schema::dropIfExists('production_sessions');
    }

    public function down(): void
    {
        Schema::create('production_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_code')->unique();
            $table->string('process_name')->nullable();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_subgroup_id')->nullable()->constrained('material_subgroups');
            $table->date('process_date');
            $table->time('process_start')->nullable();
            $table->time('process_end')->nullable();
            $table->string('operator_name')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('production_inputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_subgroup_id')->constrained('material_subgroups');
            $table->decimal('input_weight_kg', 10, 2);
            $table->timestamps();
        });

        Schema::create('production_outputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_type_id')->constrained();
            $table->foreignId('material_subgroup_id')->nullable()->constrained('material_subgroups')->nullOnDelete();
            $table->enum('output_type', ['utama', 'sampingan'])->default('utama');
            $table->decimal('output_weight_kg', 10, 2);
            $table->enum('kondisi', ['box_sementara', 'keranjang'])->default('keranjang');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
};
