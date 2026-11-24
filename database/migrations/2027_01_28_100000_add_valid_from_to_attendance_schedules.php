<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de jornada: a schedule has a start date (`valid_from`) and a person may have many, one per start date; each day
 * is read with the schedule in effect that day. The schedules that already exist apply «since always» (1970-01-01), so
 * nothing already computed changes. Going back keeps one per person, the one in effect today.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_schedules', function (Blueprint $table) {
            $table->dropUnique(['employee_id']);
            $table->date('valid_from')->default('1970-01-01');
        });

        Schema::table('attendance_schedules', function (Blueprint $table) {
            $table->unique(['employee_id', 'valid_from']);
        });

        DB::unprepared('ALTER TABLE attendance_schedules ALTER COLUMN valid_from DROP DEFAULT');
    }

    public function down(): void
    {
        // The one in effect today (the latest that has started), else the first to start.
        DB::delete(<<<'SQL'
            DELETE FROM attendance_schedules WHERE id NOT IN (
                SELECT DISTINCT ON (employee_id) id FROM attendance_schedules
                ORDER BY employee_id, (valid_from > ?::date), (CASE WHEN valid_from <= ?::date THEN valid_from END) DESC NULLS LAST, valid_from
            )
        SQL, [now()->toDateString(), now()->toDateString()]);

        Schema::table('attendance_schedules', function (Blueprint $table) {
            $table->dropUnique(['employee_id', 'valid_from']);
            $table->dropColumn('valid_from');
        });

        Schema::table('attendance_schedules', function (Blueprint $table) {
            $table->unique('employee_id');
        });
    }
};
