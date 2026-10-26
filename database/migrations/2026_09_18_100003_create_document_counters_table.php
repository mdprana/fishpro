<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penomoran dokumen yang aman dari race condition.
 *
 * Mengambil nomor urut dengan `MAX(...) + 1` gagal saat dua operator submit
 * bersamaan (dua-duanya membaca angka yang sama). Tabel ini dikunci dengan
 * lockForUpdate di App\Services\CodeGenerator sehingga nomor tidak pernah kembar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_counters', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 191)->unique();   // mis. RM-260918-NJY-YFW | CUT-260918
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_counters');
    }
};
