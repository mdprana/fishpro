<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lengkapi penerimaan (bongkar) sebagai dokumen resmi asal bahan baku.
 *
 * - purchase_order_id menjadi WAJIB: permintaan pabrik adalah barang masuk
 *   terhubung langsung dengan nomor PO.
 * - supplier_name (teks bebas) dibuang; supplier_id sudah menggantikannya.
 * - split_per_container menentukan apakah tiap keranjang/palet mendapat
 *   batch + label QR sendiri, atau cukup satu batch induk untuk seluruh kiriman.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Penerimaan lama tanpa PO tidak bisa dipaksa NOT NULL. Tidak ada
        // tebakan yang aman di sini, jadi hentikan migrasi dengan pesan jelas.
        $tanpaPo = DB::table('receivings')->whereNull('purchase_order_id')->count();
        if ($tanpaPo > 0) {
            throw new RuntimeException(
                "Ada {$tanpaPo} penerimaan tanpa nomor PO. Lengkapi dulu lewat menu Bahan Baku "
                . "sebelum menjalankan migrasi ini, karena kolom PO akan menjadi wajib."
            );
        }

        Schema::table('receivings', function (Blueprint $table) {
            $table->string('doc_number', 100)->nullable()->after('batch_code');     // no. surat jalan
            $table->string('vehicle_number', 30)->nullable()->after('doc_number');
            $table->time('unload_start')->nullable()->after('received_date');
            $table->time('unload_end')->nullable()->after('unload_start');
            $table->decimal('temperature_c', 5, 2)->nullable()->after('unload_end');
            $table->boolean('split_per_container')->default(false)->after('container_count');
            $table->foreignId('updated_by')->nullable()->after('created_by')
                ->constrained('users')->nullOnDelete();
        });

        Schema::table('receivings', function (Blueprint $table) {
            $table->dropColumn('supplier_name');
        });

        // FK lama memakai nullOnDelete, yang mustahil bila kolomnya NOT NULL.
        // Dilepas dulu, lalu dipasang kembali sebagai restrict: PO yang sudah
        // punya penerimaan memang tidak boleh dihapus permanen.
        Schema::table('receivings', function (Blueprint $table) {
            $table->dropForeign(['purchase_order_id']);
        });

        Schema::table('receivings', function (Blueprint $table) {
            $table->unsignedBigInteger('purchase_order_id')->nullable(false)->change();
        });

        Schema::table('receivings', function (Blueprint $table) {
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('receivings', function (Blueprint $table) {
            $table->dropForeign(['purchase_order_id']);
        });

        Schema::table('receivings', function (Blueprint $table) {
            $table->unsignedBigInteger('purchase_order_id')->nullable()->change();
        });

        Schema::table('receivings', function (Blueprint $table) {
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->nullOnDelete();
            $table->string('supplier_name')->nullable()->after('container_count');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn([
                'doc_number', 'vehicle_number', 'unload_start',
                'unload_end', 'temperature_c', 'split_per_container',
            ]);
        });
    }
};
