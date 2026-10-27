<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak aksi bisnis, append-only. Tanpa FK supaya penghapusan user/sumber
 * tidak pernah menulis ulang riwayat. Trigger menolak UPDATE/DELETE, termasuk
 * dari query builder; admin DB tetap bisa mencabut trigger, jadi ini bukan
 * jaminan anti-rusak kriptografis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_id')->nullable(); // null = sistem
            $table->dateTime('recorded_at');                    // UTC, waktu tulis server
            $table->string('entity_type', 30);
            $table->unsignedBigInteger('entity_id');
            $table->string('action', 50);
            $table->unsignedBigInteger('movement_id')->nullable()->unique();
            $table->json('before')->nullable();
            $table->json('after')->nullable();

            $table->index(['recorded_at', 'id']);
            $table->index(['entity_type', 'entity_id', 'id']);
            $table->index(['actor_id', 'id']);
        });

        $msg = 'audit_events bersifat append-only';

        foreach (['UPDATE' => 'no_update', 'DELETE' => 'no_delete'] as $event => $name) {
            $body = match (DB::getDriverName()) {
                'sqlite'           => "BEGIN SELECT RAISE(ABORT, '{$msg}'); END",
                'mysql', 'mariadb' => "FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$msg}'",
                default            => throw new RuntimeException('Driver DB tidak didukung untuk trigger audit_events.'),
            };

            DB::unprepared("CREATE TRIGGER audit_events_{$name} BEFORE {$event} ON audit_events {$body}");
        }
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS audit_events_no_update');
        DB::unprepared('DROP TRIGGER IF EXISTS audit_events_no_delete');
        Schema::dropIfExists('audit_events');
    }
};
