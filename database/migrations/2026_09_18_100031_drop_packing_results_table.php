<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `packing_results` digantikan sepenuhnya oleh packing_boxes + packing_box_sources.
 *
 * Tabel lama menyimpan `box_count` (satu baris = banyak box) sehingga tidak
 * bisa memberi satu QR per box maupun mencatat berat yang berbeda antar box,
 * dua hal yang menjadi syarat penelusuran per box. Semua pembacanya
 * (PoItem, PurchaseOrder, DashboardService, view PO) sudah dialihkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('packing_results');
    }

    public function down(): void
    {
        Schema::create('packing_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('po_item_id')->nullable()->constrained('po_items')->nullOnDelete();
            $table->foreignId('product_type_id')->constrained();
            $table->integer('box_count');
            $table->decimal('weight_kg', 10, 2);
            $table->decimal('weight_per_box_kg', 8, 4);
            $table->string('box_label')->nullable();
            $table->enum('packing_status', ['packed', 'stored', 'shipped'])->default('packed');
            $table->date('packing_date');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
