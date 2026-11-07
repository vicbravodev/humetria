<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de jornada: the link RH sends a person to link a device (enrollment). Only the hash of its token is kept. It is
 * good once and for 48 hours; a person has at most one pending link (a new one revokes the one before), which the partial
 * unique index holds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_enrollment_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('employee_id');
            $table->char('token_hash', 64)->unique();
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('expires_at');
            $table->timestampTz('used_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();

            $table->foreign(['employee_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('employees')->restrictOnDelete();
        });

        DB::unprepared(<<<'SQL'
            CREATE UNIQUE INDEX attendance_enrollment_links_one_pending
                ON attendance_enrollment_links (tenant_id, employee_id)
                WHERE used_at IS NULL AND revoked_at IS NULL;
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_enrollment_links');
    }
};
