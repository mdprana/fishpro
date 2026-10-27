<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Tanda terima impor CSV yang sudah di-commit; unik per nonce dan per isi. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receiving_imports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_id');
            $table->uuid('nonce')->unique();
            $table->char('content_hash', 64)->unique();
            $table->unsignedInteger('row_count');
            $table->json('receiving_ids');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receiving_imports');
    }
};
