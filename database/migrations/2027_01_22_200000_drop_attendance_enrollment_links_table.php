<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The link to link a phone of the registro de jornada goes: a person creates their key with the link RH issues them from
 * «Accesos» (worker_access_links). No company uses the platform yet, so no row is kept. Going back makes the table again
 * as it stood (a link of the person themselves has no `issued_by`), empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('attendance_enrollment_links');
    }

    public function down(): void
    {
        Schema::create('attendance_enrollment_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('employee_id');
            $table->char('token_hash', 64)->unique();
            $table->foreignId('issued_by')->nullable()->constrained('users')->restrictOnDelete();
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
};
