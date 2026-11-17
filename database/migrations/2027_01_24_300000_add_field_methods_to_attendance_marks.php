<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Registro de jornada: the marks of a field center (criterio 5.3), with a good position (`field`) or without one
 * (`field_unlocated`). The marks stay insert-only: only the list of known methods grows.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE attendance_marks
                DROP CONSTRAINT attendance_marks_method_known,
                ADD CONSTRAINT attendance_marks_method_known CHECK (method IN ('qr_and_geofence', 'geofence', 'qr', 'remote', 'trusted_browser', 'correction', 'field', 'field_unlocated'));
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE attendance_marks
                DROP CONSTRAINT attendance_marks_method_known,
                ADD CONSTRAINT attendance_marks_method_known CHECK (method IN ('qr_and_geofence', 'geofence', 'qr', 'remote', 'trusted_browser', 'correction'));
        SQL);
    }
};
