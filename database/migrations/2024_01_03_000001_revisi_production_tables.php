<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Revisi production_sessions: support multi-subgroup
        // Hapus kolom material_subgroup_id & input_weight_kg dari sessions
        // Karena sekarang input bisa dari banyak subgroup
        Schema::table('production_sessions', function (Blueprint $table) {
            $table->string('process_name')->nullable()->after('session_code'); // nama proses, mis: Potong Steak
            $table->dropForeign(['material_subgroup_id']);
            $table->dropColumn(['material_subgroup_id', 'input_weight_kg']);
        });

        // Tabel baru: production_inputs (bahan baku per sesi, bisa banyak subgroup)
        Schema::create('production_inputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_subgroup_id')->constrained();
            $table->decimal('input_weight_kg', 10, 2);
            $table->timestamps();
        });

        // Revisi production_outputs: tambah kondisi & tipe (utama vs sampingan)
        Schema::table('production_outputs', function (Blueprint $table) {
            $table->foreignId('material_subgroup_id')->nullable()->after('production_session_id')
                  ->constrained('material_subgroups')->nullOnDelete(); // null = output sampingan gabungan
            $table->enum('output_type', ['utama', 'sampingan'])->default('utama')->after('product_type_id');
            $table->enum('kondisi', ['box_sementara', 'keranjang'])->default('keranjang')->after('output_weight_kg');
        });
    }

    public function down(): void
    {
        Schema::table('production_outputs', function (Blueprint $table) {
            $table->dropForeign(['material_subgroup_id']);
            $table->dropColumn(['material_subgroup_id', 'output_type', 'kondisi']);
        });
        Schema::dropIfExists('production_inputs');
        Schema::table('production_sessions', function (Blueprint $table) {
            $table->dropColumn('process_name');
            $table->foreignId('material_subgroup_id')->constrained();
            $table->decimal('input_weight_kg', 10, 2)->default(0);
        });
    }
};
