<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de jornada: the QR screens of a work center (a fixed tablet). Only the hash of a screen's token is kept; RH
 * revokes a screen and its row stays, with when.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_kiosk_devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('work_center_id');
            $table->char('token_hash', 64)->unique();
            $table->timestampTz('last_seen_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();

            $table->foreign(['work_center_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('work_centers')->restrictOnDelete();
            $table->index(['tenant_id', 'work_center_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_kiosk_devices');
    }
};
