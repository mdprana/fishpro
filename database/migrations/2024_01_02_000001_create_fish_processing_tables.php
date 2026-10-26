<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ════════════════════════════════════════════════════════
        // 1. MATERIAL GROUPS: Group Bahan Baku
        //    Contoh: Ayam Beku, YF LOIN, BE LOIN
        // ════════════════════════════════════════════════════════
        Schema::create('material_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('unit')->default('kg');
            $table->string('color', 7)->default('#6c757d');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ════════════════════════════════════════════════════════
        // 2. MATERIAL SUBGROUPS: Subgroup Bahan Baku (item aktual)
        //    Contoh: Ayam Boiler, Ayam Kampung (di bawah Ayam Beku)
        // ════════════════════════════════════════════════════════
        Schema::create('material_subgroups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_group_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ════════════════════════════════════════════════════════
        // 3. PRODUCT GROUPS: Group Produk
        //    Contoh: Ikan, Ayam, Seafood, Frozen Food, Ready To Cook
        // ════════════════════════════════════════════════════════
        Schema::create('product_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color', 7)->default('#6b7280');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ════════════════════════════════════════════════════════
        // 4. PRODUCT TYPES: Tipe Produk (di bawah Group Produk)
        //    Contoh: BE STEAK, YF STEAK, YF LOIN, BE LOIN (grup Ikan)
        // ════════════════════════════════════════════════════════
        Schema::create('product_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('color', 7)->nullable(); // null = pakai warna grup
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ════════════════════════════════════════════════════════
        // 5. YIELD ESTIMATES: Estimasi % hasil Subgroup -> Produk
        //    Contoh: Ayam Boiler -> Dada Ayam 40%, Paha Ayam 32%, dst
        // ════════════════════════════════════════════════════════
        Schema::create('yield_estimates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_subgroup_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_type_id')->constrained()->cascadeOnDelete();
            $table->decimal('percentage', 5, 2);
            $table->timestamps();
            $table->unique(['material_subgroup_id','product_type_id'], 'uniq_subgroup_product');
        });

        // ════════════════════════════════════════════════════════
        // 6. PURCHASE ORDERS
        // ════════════════════════════════════════════════════════
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number')->unique();
            $table->string('buyer_name');
            $table->date('order_date');
            $table->date('deadline');
            $table->enum('status', ['draft','active','in_progress','completed','cancelled'])->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        // ════════════════════════════════════════════════════════
        // 7. PO ITEMS: multi-produk per PO, kalkulasi 2 arah
        // ════════════════════════════════════════════════════════
        Schema::create('po_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_type_id')->constrained();
            $table->decimal('target_weight_kg', 10, 2);
            $table->integer('target_box');
            $table->decimal('weight_per_box_kg', 8, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // ════════════════════════════════════════════════════════
        // 8. STOK BAHAN BAKU: per subgroup
        // ════════════════════════════════════════════════════════
        Schema::create('raw_material_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_subgroup_id')->constrained()->cascadeOnDelete();
            $table->decimal('current_stock_kg', 12, 2)->default(0);
            $table->timestamps();
        });

        // ════════════════════════════════════════════════════════
        // 9. RECEIVINGS: per subgroup
        // ════════════════════════════════════════════════════════
        Schema::create('receivings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_subgroup_id')->constrained();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('batch_code')->nullable();
            $table->decimal('weight_kg', 10, 2);
            $table->string('container_type', 100)->default('Box Polos');
            $table->integer('container_count')->default(1);
            $table->string('supplier_name')->nullable();
            $table->date('received_date');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        // ════════════════════════════════════════════════════════
        // 10. PRODUCTION SESSIONS: per subgroup
        // ════════════════════════════════════════════════════════
        Schema::create('production_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_code')->unique();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_subgroup_id')->constrained();
            $table->decimal('input_weight_kg', 10, 2);
            $table->date('process_date');
            $table->time('process_start')->nullable();
            $table->time('process_end')->nullable();
            $table->string('operator_name')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        // ════════════════════════════════════════════════════════
        // 11. PRODUCTION OUTPUTS
        // ════════════════════════════════════════════════════════
        Schema::create('production_outputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_type_id')->constrained();
            $table->decimal('output_weight_kg', 10, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // ════════════════════════════════════════════════════════
        // 12. PACKING RESULTS
        // ════════════════════════════════════════════════════════
        Schema::create('packing_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('po_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_type_id')->constrained();
            $table->integer('box_count');
            $table->decimal('weight_kg', 10, 2);
            $table->decimal('weight_per_box_kg', 8, 4);
            $table->string('box_label')->nullable();
            $table->enum('packing_status', ['packed','stored','shipped'])->default('packed');
            $table->date('packing_date');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        // ════════════════════════════════════════════════════════
        // 13. PO SURPLUSES: rekap sisa/kelebihan proses & packing
        // ════════════════════════════════════════════════════════
        Schema::create('po_surpluses', function (Blueprint $table) {
            $table->id();
            $table->string('surplus_code')->unique();
            $table->foreignId('source_po_id')->constrained('purchase_orders');
            $table->foreignId('product_type_id')->constrained('product_types');
            $table->enum('surplus_type', ['process','packing']);
            $table->decimal('weight_kg', 10, 2);
            $table->integer('box_count')->default(0);
            $table->enum('status', ['available','allocated','used'])->default('available');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('po_surpluses');
        Schema::dropIfExists('packing_results');
        Schema::dropIfExists('production_outputs');
        Schema::dropIfExists('production_sessions');
        Schema::dropIfExists('receivings');
        Schema::dropIfExists('raw_material_stocks');
        Schema::dropIfExists('po_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('yield_estimates');
        Schema::dropIfExists('product_types');
        Schema::dropIfExists('product_groups');
        Schema::dropIfExists('material_subgroups');
        Schema::dropIfExists('material_groups');
    }
};
