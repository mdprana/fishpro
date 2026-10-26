<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FASE 0: Hotfix bug perusak data.
 *
 * Sebelumnya `packing_results.po_item_id` memakai cascadeOnDelete dan
 * `PurchaseOrderController@update` menjalankan `$po->items()->delete()`.
 * Akibatnya: MENGEDIT PO menghapus SELURUH riwayat packing PO tersebut.
 *
 * Perbaikan dua sisi:
 *  1. po_items pakai softDeletes  -> delete tidak lagi menyentuh baris DB
 *  2. FK po_item_id jadi nullOnDelete -> hard delete pun tidak menghapus packing
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('po_items', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('packing_results', function (Blueprint $table) {
            $table->dropForeign(['po_item_id']);
        });

        // po_item_id harus nullable agar nullOnDelete valid
        Schema::table('packing_results', function (Blueprint $table) {
            $table->unsignedBigInteger('po_item_id')->nullable()->change();
        });

        Schema::table('packing_results', function (Blueprint $table) {
            $table->foreign('po_item_id')->references('id')->on('po_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('packing_results', function (Blueprint $table) {
            $table->dropForeign(['po_item_id']);
        });

        Schema::table('packing_results', function (Blueprint $table) {
            $table->unsignedBigInteger('po_item_id')->nullable(false)->change();
        });

        Schema::table('packing_results', function (Blueprint $table) {
            $table->foreign('po_item_id')->references('id')->on('po_items')->cascadeOnDelete();
        });

        Schema::table('po_items', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
