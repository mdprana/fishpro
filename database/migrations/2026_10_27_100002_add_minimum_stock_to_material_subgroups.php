<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_subgroups', function (Blueprint $table) {
            // null = belum diatur; 0 tetap batas minimum yang sah.
            $table->decimal('minimum_stock_kg', 12, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('material_subgroups', function (Blueprint $table) {
            $table->dropColumn('minimum_stock_kg');
        });
    }
};
