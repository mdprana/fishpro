<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kode singkat untuk master, dipakai membentuk nomor batch & box yang rapi:
 *   material_subgroups.code -> RM-260918-NJY-{YFW}-001
 *   product_types.code      -> BOX-PO2024001-{YFSTK}-0187
 *
 * Nullable: bila kosong, CodeGenerator menurunkan singkatan dari nama.
 * Keunikan nomor tetap dijamin counter, bukan oleh kode ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_subgroups', function (Blueprint $table) {
            $table->string('code', 8)->nullable()->after('name');
        });

        Schema::table('product_types', function (Blueprint $table) {
            $table->string('code', 8)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('material_subgroups', function (Blueprint $table) {
            $table->dropColumn('code');
        });

        Schema::table('product_types', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }
};
