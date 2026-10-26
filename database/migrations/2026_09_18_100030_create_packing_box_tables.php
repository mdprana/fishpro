<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Packing per box fisik.
 *
 * Tabel lama `packing_results` menyimpan `box_count`, jadi satu baris mewakili
 * banyak box, sehingga mustahil memberi satu QR per box atau mencatat berat
 * yang berbeda-beda antar box. Di sini satu baris = satu box nyata.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Satu sesi packing: sekumpulan box yang dibuat bersama.
        Schema::create('packing_runs', function (Blueprint $table) {
            $table->id();
            $table->string('run_code', 60)->unique();      // PKR-260921-0003
            $table->foreignId('purchase_order_id')->constrained();
            $table->foreignId('po_item_id')->nullable()->constrained('po_items')->nullOnDelete();
            $table->foreignId('product_type_id')->constrained();
            $table->enum('mode', ['bulk', 'per_box']);
            $table->unsignedInteger('planned_box_count')->default(0);
            $table->decimal('planned_weight_per_box', 8, 3)->nullable();
            $table->date('packing_date');
            $table->string('operator_name', 200)->nullable();
            $table->enum('status', ['open', 'closed', 'voided'])->default('open');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['purchase_order_id', 'packing_date']);
        });

        Schema::create('packing_boxes', function (Blueprint $table) {
            $table->id();
            $table->string('box_code', 80)->unique();      // BOX-PO2024001-YFSTK-0187
            $table->string('qr_code', 64)->unique();
            $table->foreignId('packing_run_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_order_id')->constrained();
            // nullOnDelete, BUKAN cascade: menghapus item PO tidak boleh ikut
            // menghapus bukti fisik box yang sudah dipacking.
            $table->foreignId('po_item_id')->nullable()->constrained('po_items')->nullOnDelete();
            $table->foreignId('product_type_id')->constrained();
            // Batch penyumbang terbesar, untuk ditampilkan cepat tanpa join.
            $table->foreignId('primary_batch_id')->nullable()->constrained('production_batches')->nullOnDelete();

            $table->unsignedInteger('sequence_no');
            $table->decimal('net_weight_kg', 10, 3);
            $table->decimal('gross_weight_kg', 10, 3)->nullable();
            $table->decimal('tare_kg', 8, 3)->nullable();
            $table->boolean('is_mixed_batch')->default(false);

            $table->enum('status', ['draft', 'packed', 'stored', 'shipped', 'voided'])->default('packed');
            $table->date('packing_date');
            $table->dateTime('weighed_at')->nullable();
            $table->timestamp('label_printed_at')->nullable();
            $table->string('operator_name', 200)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['purchase_order_id', 'product_type_id', 'status'], 'idx_box_po_product_status');
            $table->index('packing_date');
            $table->index('primary_batch_id');
        });

        /**
         * Isi sebuah box bisa berasal dari beberapa batch sekaligus.
         *
         * root_batch_id dan supplier_id sengaja didenormalisasi di sini supaya
         * rekap "bahan baku dari supplier mana saja" dan "box mana yang batch-nya
         * berbeda" cukup satu GROUP BY, bukan rekursi PHP untuk tiap box.
         */
        Schema::create('packing_box_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('packing_box_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained('production_batches');
            $table->foreignId('root_batch_id')->nullable()->constrained('production_batches')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->decimal('contributed_kg', 10, 3);
            $table->decimal('contribution_pct', 6, 3)->default(0);
            $table->timestamps();

            // Satu lot akhir bisa berasal dari beberapa batch induk sekaligus,
            // jadi keunikannya harus memuat root_batch_id. Tanpa itu hanya satu
            // asal yang tersimpan dan rekap per supplier jadi salah.
            $table->unique(['packing_box_id', 'batch_id', 'root_batch_id'], 'uniq_box_batch_root');
            $table->index('batch_id');
            $table->index('root_batch_id');
        });

        Schema::table('batch_movements', function (Blueprint $table) {
            $table->foreign('packing_box_id')->references('id')->on('packing_boxes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('batch_movements', fn (Blueprint $t) => $t->dropForeign(['packing_box_id']));
        Schema::dropIfExists('packing_box_sources');
        Schema::dropIfExists('packing_boxes');
        Schema::dropIfExists('packing_runs');
    }
};
