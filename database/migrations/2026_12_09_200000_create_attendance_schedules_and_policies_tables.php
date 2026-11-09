<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de jornada: the agreed weekly schedule of each person (criterio 2.6), with their kind of shift and where they
 * work, and the one policy of each company (criterios 4.1 to 4.4 and 7.1). The legal caps do not live here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('employee_id')->unique();
            $table->string('shift_type', 5);
            $table->string('work_mode', 6);
            $table->jsonb('minutes_by_weekday');
            $table->timestampsTz();

            $table->foreign(['employee_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('employees')->restrictOnDelete();
        });

        Schema::create('attendance_policies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->unique()->constrained()->restrictOnDelete();
            $table->string('out_of_zone', 14)->default('block');
            $table->unsignedSmallInteger('max_accuracy_m')->default(100);
            $table->boolean('require_qr_onsite')->default(true);
            $table->boolean('trusted_may_use_browser')->default(true);
            $table->unsignedSmallInteger('retention_months')->default(12);
            $table->timestampsTz();
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE attendance_schedules
                ADD CONSTRAINT attendance_schedules_shift_type_known CHECK (shift_type IN ('day', 'night', 'mixed')),
                ADD CONSTRAINT attendance_schedules_work_mode_known CHECK (work_mode IN ('onsite', 'remote'));

            ALTER TABLE attendance_policies
                ADD CONSTRAINT attendance_policies_out_of_zone_known CHECK (out_of_zone IN ('block', 'allow_and_flag')),
                ADD CONSTRAINT attendance_policies_accuracy_positive CHECK (max_accuracy_m > 0),
                ADD CONSTRAINT attendance_policies_retention_at_least_12 CHECK (retention_months >= 12);
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_policies');
        Schema::dropIfExists('attendance_schedules');
    }
};
