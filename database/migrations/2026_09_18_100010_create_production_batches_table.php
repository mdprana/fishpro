<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identitas setiap unit fisik yang bisa dipegang orang:
 * palet/keranjang bahan baku, lot hasil tiap tahap, sampai lot siap packing.
 *
 * level 0  = Batch Number Induk (Parent Lot) hasil penerimaan
 * level 1+ = turunan (wadah, hasil potong, hasil trimming, hasil steak)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_number', 191)->unique();
            $table->string('qr_code', 64)->unique();

            // Silsilah. parent_batch_id hanya untuk tampilan dan awalan nomor.
            // Penelusuran sesungguhnya memakai batch_stage_links karena satu lot
            // bisa berasal dari beberapa induk sekaligus.
            $table->foreignId('parent_batch_id')->nullable()->constrained('production_batches')->nullOnDelete();
            $table->foreignId('root_batch_id')->nullable()->constrained('production_batches')->nullOnDelete();
            $table->unsignedTinyInteger('level')->default(0);
            $table->boolean('is_multi_source')->default(false);

            $table->foreignId('process_stage_id')->nullable()->constrained('process_stages');
            $table->enum('origin', ['receiving', 'stage', 'repack'])->default('stage');

            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('material_subgroup_id')->nullable()->constrained('material_subgroups');
            $table->foreignId('product_type_id')->nullable()->constrained('product_types');
            $table->foreignId('receiving_id')->nullable()->constrained('receivings')->nullOnDelete();
            // FK dipasang setelah batch_stage_records dibuat (referensi melingkar).
            $table->unsignedBigInteger('batch_stage_record_id')->nullable();

            $table->enum('content_type', ['raw', 'wip', 'byproduct', 'finished'])->default('wip');
            $table->decimal('initial_weight_kg', 12, 3);
            $table->decimal('current_weight_kg', 12, 3)->default(0); // cache dari ledger
            $table->string('container_type', 100)->nullable();
            $table->unsignedInteger('container_count')->default(1);

            // Tidak memakai softDeletes: pembatalan ditandai status `voided`
            // + baris reversal di ledger, supaya jejak audit tidak hilang.
            $table->enum('status', ['available', 'partial', 'consumed', 'packed', 'voided'])->default('available');

            $table->dateTime('produced_at');
            // Tanggal tahap ini: bongkar / potong / trimming / steak.
            // Inilah sumber kolom tanggal di kartu telusur.
            $table->date('effective_date');
            $table->timestamp('label_printed_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['purchase_order_id', 'process_stage_id']);
            $table->index(['status', 'process_stage_id']);
            $table->index('root_batch_id');
            $table->index(['supplier_id', 'effective_date']);
            $table->index(['material_subgroup_id', 'status']);
            $table->index('batch_stage_record_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_batches');
    }
};
