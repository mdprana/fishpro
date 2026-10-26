<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hubungkan penerimaan ke master supplier.
 *
 * Kolom lama `supplier_name` (teks bebas) sengaja BELUM dihapus di sini.
 * Ia masih menjadi sumber backfill dan baru dilepas pada tahap batch/QR,
 * setelah form penerimaan diubah memakai dropdown supplier.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receivings', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('purchase_order_id')
                ->constrained('suppliers')->nullOnDelete();
            $table->index(['received_date', 'supplier_id']);
        });

        $this->backfillFromSupplierName();
    }

    /**
     * Cocokkan supplier_name lama ke master. Yang belum ada dibuatkan otomatis
     * supaya tidak ada penerimaan yang kehilangan asal-usulnya.
     */
    private function backfillFromSupplierName(): void
    {
        $names = DB::table('receivings')
            ->whereNotNull('supplier_name')->where('supplier_name', '<>', '')
            ->distinct()->pluck('supplier_name');

        foreach ($names as $name) {
            $supplierId = DB::table('suppliers')->where('name', $name)->value('id');

            if (!$supplierId) {
                $supplierId = DB::table('suppliers')->insertGetId([
                    'code'       => $this->uniqueCodeFor($name),
                    'name'       => $name,
                    'is_active'  => true,
                    'notes'      => 'Dibuat otomatis saat migrasi dari supplier_name teks bebas.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('receivings')->where('supplier_name', $name)->update(['supplier_id' => $supplierId]);
        }
    }

    /** Singkatan dari nama, ditambah angka bila sudah terpakai. */
    private function uniqueCodeFor(string $name): string
    {
        $words = preg_split('/[\s\-_.]+/', strtoupper($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        // Buang bentuk badan usaha supaya inisialnya bermakna: "UD Nelayan Jaya" -> NJ
        $words = array_values(array_filter($words, fn ($w) => !in_array($w, ['UD', 'PT', 'CV', 'PD', 'TBK'], true)));

        $base = substr(preg_replace('/[^A-Z0-9]/', '',
            implode('', array_map(fn ($w) => substr($w, 0, 1), $words))
        ) ?: 'SUP', 0, 6) ?: 'SUP';

        $code   = $base;
        $suffix = 1;
        while (DB::table('suppliers')->where('code', $code)->exists()) {
            $code = substr($base, 0, 8) . $suffix++;
        }

        return $code;
    }

    public function down(): void
    {
        Schema::table('receivings', function (Blueprint $table) {
            $table->dropIndex(['received_date', 'supplier_id']);
            $table->dropConstrainedForeignId('supplier_id');
        });
    }
};
