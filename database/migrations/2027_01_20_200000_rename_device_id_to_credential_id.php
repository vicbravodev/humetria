<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * What a person signed with is their key (worker_credentials), not a device: the marks, the weekly confirmations, the
 * correction requests and the acceptances of the agreement name it `credential_id`. Postgres carries the column into its
 * CHECKs (the weekly confirmations' status_consistent); the foreign keys keep their target and take the new name.
 */
return new class extends Migration
{
    private const array TABLES = [
        'attendance_marks',
        'attendance_weekly_confirmations',
        'attendance_correction_requests',
        'attendance_agreement_acceptances',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            DB::statement("ALTER TABLE {$table} RENAME COLUMN device_id TO credential_id");
            DB::statement("ALTER TABLE {$table} RENAME CONSTRAINT {$table}_device_id_tenant_id_foreign TO {$table}_credential_id_tenant_id_foreign");
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            DB::statement("ALTER TABLE {$table} RENAME CONSTRAINT {$table}_credential_id_tenant_id_foreign TO {$table}_device_id_tenant_id_foreign");
            DB::statement("ALTER TABLE {$table} RENAME COLUMN credential_id TO device_id");
        }
    }
};
