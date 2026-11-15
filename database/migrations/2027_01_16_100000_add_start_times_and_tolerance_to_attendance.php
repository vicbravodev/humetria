<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de jornada: the optional start time of each weekday of a schedule (minutes from local midnight, null where RH set
 * none) and the company's tolerance for the lateness it shows (informational: it takes no minutes from anyone). A schedule
 * without start times behaves as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_schedules', function (Blueprint $table) {
            $table->jsonb('starts_by_weekday')->nullable();
        });

        Schema::table('attendance_policies', function (Blueprint $table) {
            $table->unsignedSmallInteger('late_tolerance_minutes')->default(10);
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE attendance_policies
                ADD CONSTRAINT attendance_policies_late_tolerance_range CHECK (late_tolerance_minutes BETWEEN 0 AND 120);
        SQL);
    }

    public function down(): void
    {
        Schema::table('attendance_policies', function (Blueprint $table) {
            $table->dropColumn('late_tolerance_minutes');
        });

        Schema::table('attendance_schedules', function (Blueprint $table) {
            $table->dropColumn('starts_by_weekday');
        });
    }
};
