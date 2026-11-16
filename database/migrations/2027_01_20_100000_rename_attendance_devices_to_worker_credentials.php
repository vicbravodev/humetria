<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The key of a person is no longer the time clock's: WorkerAccess owns it. The table keeps its rows and its foreign keys
 * (the marks, the confirmations, the corrections and the acceptances still point to it); it gains how and why it came and went.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('attendance_devices', 'worker_credentials');

        Schema::table('worker_credentials', function (Blueprint $table) {
            $table->renameColumn('enrolled_at', 'registered_at');
            $table->string('registered_via', 20)->default('issued_link');
            $table->string('revoked_reason', 20)->nullable();
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE worker_credentials
                RENAME CONSTRAINT attendance_devices_id_tenant_unique TO worker_credentials_id_tenant_unique;
            ALTER TABLE worker_credentials
                RENAME CONSTRAINT attendance_devices_sign_count_in_range TO worker_credentials_sign_count_in_range;
            ALTER TABLE worker_credentials
                RENAME CONSTRAINT attendance_devices_label_filled TO worker_credentials_label_filled;
            ALTER TABLE worker_credentials
                ADD CONSTRAINT worker_credentials_registered_via_valid CHECK (registered_via IN ('issued_link', 'session')),
                ADD CONSTRAINT worker_credentials_revoked_reason_valid CHECK (
                    (revoked_at IS NULL AND revoked_reason IS NULL)
                    OR (revoked_at IS NOT NULL AND (revoked_reason IS NULL OR revoked_reason IN ('reissued', 'revoked_by_hr', 'not_me')))
                );
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE worker_credentials
                DROP CONSTRAINT worker_credentials_registered_via_valid,
                DROP CONSTRAINT worker_credentials_revoked_reason_valid;
            ALTER TABLE worker_credentials RENAME CONSTRAINT worker_credentials_label_filled TO attendance_devices_label_filled;
            ALTER TABLE worker_credentials RENAME CONSTRAINT worker_credentials_sign_count_in_range TO attendance_devices_sign_count_in_range;
            ALTER TABLE worker_credentials RENAME CONSTRAINT worker_credentials_id_tenant_unique TO attendance_devices_id_tenant_unique;
        SQL);

        Schema::table('worker_credentials', function (Blueprint $table) {
            $table->dropColumn(['registered_via', 'revoked_reason']);
            $table->renameColumn('registered_at', 'enrolled_at');
        });

        Schema::rename('worker_credentials', 'attendance_devices');
    }
};
