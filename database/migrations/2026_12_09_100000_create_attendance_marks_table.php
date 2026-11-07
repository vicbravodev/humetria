<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de jornada: the marks of each person (criterios 3.1 to 3.4, 5.3 and 5.4). Append-only like audit_entries: a
 * Postgres trigger rejects UPDATE, DELETE and TRUNCATE, so a correction is always a new mark that points to its original.
 * zone_id and device_id get their foreign keys with the zones and devices tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_marks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('employee_id');
            $table->uuid('work_center_id');
            $table->string('type', 11);
            $table->timestampTz('marked_at', 6)->nullable();
            $table->string('method', 15);
            $table->uuid('zone_id')->nullable();
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->float('accuracy_m')->nullable();
            $table->float('distance_m')->nullable();
            $table->uuid('device_id')->nullable();
            $table->uuid('corrects_mark_id')->nullable()->unique();
            $table->foreignId('corrected_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign(['employee_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('employees')->restrictOnDelete();
            $table->foreign(['work_center_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('work_centers')->restrictOnDelete();
            $table->unique(['id', 'tenant_id'], 'attendance_marks_id_tenant_unique');
            $table->index(['tenant_id', 'employee_id', 'marked_at']);
            $table->index(['tenant_id', 'work_center_id', 'marked_at']);
        });

        Schema::table('attendance_marks', function (Blueprint $table) {
            $table->foreign(['corrects_mark_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('attendance_marks')->restrictOnDelete();
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE attendance_marks
                ADD CONSTRAINT attendance_marks_type_known CHECK (type IN ('start', 'break_start', 'break_end', 'end')),
                ADD CONSTRAINT attendance_marks_method_known CHECK (method IN ('qr_and_geofence', 'geofence', 'qr', 'remote', 'trusted_browser', 'correction')),
                ADD CONSTRAINT attendance_marks_correction_complete CHECK (
                    (method = 'correction') = (corrected_by IS NOT NULL AND length(btrim(coalesce(reason, ''))) > 0)
                ),
                ADD CONSTRAINT attendance_marks_points_from_a_correction CHECK (corrects_mark_id IS NULL OR method = 'correction'),
                ADD CONSTRAINT attendance_marks_time_unless_voiding CHECK (marked_at IS NOT NULL OR corrects_mark_id IS NOT NULL),
                ADD CONSTRAINT attendance_marks_coordinates_in_range CHECK (latitude BETWEEN -90 AND 90 AND longitude BETWEEN -180 AND 180);

            CREATE OR REPLACE FUNCTION attendance_marks_append_only() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'attendance_marks is append-only';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER attendance_marks_no_update_delete
                BEFORE UPDATE OR DELETE ON attendance_marks
                FOR EACH ROW EXECUTE FUNCTION attendance_marks_append_only();

            CREATE TRIGGER attendance_marks_no_truncate
                BEFORE TRUNCATE ON attendance_marks
                FOR EACH STATEMENT EXECUTE FUNCTION attendance_marks_append_only();
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_marks');
        DB::unprepared('DROP FUNCTION IF EXISTS attendance_marks_append_only()');
    }
};
