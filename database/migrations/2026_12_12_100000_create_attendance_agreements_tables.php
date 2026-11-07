<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de jornada: the versioned text of a company's agreement on the electronic record, and each person's acceptance
 * of a version on a device (criterios 1.3 and 5.1). Both are evidence that the record was agreed, so, like the marks, they
 * are append-only: a new text is a new version. device_id gets its foreign key with the devices table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_agreements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('version');
            $table->text('body');
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['tenant_id', 'version']);
            $table->unique(['id', 'tenant_id'], 'attendance_agreements_id_tenant_unique');
        });

        Schema::create('attendance_agreement_acceptances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('agreement_id');
            $table->uuid('employee_id');
            $table->uuid('device_id');
            $table->timestampTz('accepted_at');

            $table->foreign(['agreement_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('attendance_agreements')->restrictOnDelete();
            $table->foreign(['employee_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('employees')->restrictOnDelete();
            $table->index(['tenant_id', 'employee_id']);
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE attendance_agreements
                ADD CONSTRAINT attendance_agreements_version_positive CHECK (version >= 1),
                ADD CONSTRAINT attendance_agreements_body_filled CHECK (btrim(body) <> '');

            CREATE OR REPLACE FUNCTION attendance_agreements_append_only() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION '% is append-only', TG_TABLE_NAME;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER attendance_agreements_no_update_delete
                BEFORE UPDATE OR DELETE ON attendance_agreements
                FOR EACH ROW EXECUTE FUNCTION attendance_agreements_append_only();
            CREATE TRIGGER attendance_agreements_no_truncate
                BEFORE TRUNCATE ON attendance_agreements
                FOR EACH STATEMENT EXECUTE FUNCTION attendance_agreements_append_only();

            CREATE TRIGGER attendance_agreement_acceptances_no_update_delete
                BEFORE UPDATE OR DELETE ON attendance_agreement_acceptances
                FOR EACH ROW EXECUTE FUNCTION attendance_agreements_append_only();
            CREATE TRIGGER attendance_agreement_acceptances_no_truncate
                BEFORE TRUNCATE ON attendance_agreement_acceptances
                FOR EACH STATEMENT EXECUTE FUNCTION attendance_agreements_append_only();
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_agreement_acceptances');
        Schema::dropIfExists('attendance_agreements');
        DB::unprepared('DROP FUNCTION IF EXISTS attendance_agreements_append_only()');
    }
};
