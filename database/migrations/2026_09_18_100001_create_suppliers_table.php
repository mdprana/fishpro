<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master supplier. Sebelumnya supplier hanya teks bebas di `receivings.supplier_name`
 * sehingga rekap per supplier tidak mungkin akurat (typo = supplier baru).
 *
 * `code` dipakai sebagai komponen Batch Number Induk, mis. RM-260918-NJY-YFW-001.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 200);
            $table->string('contact_person', 150)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email', 150)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
