<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Registro de jornada: the marks made without a connection (criterios 3.3.1 and 5.3), with a QR that bounded their time
 * (`offline_qr`) or with the time the person declared (`offline_declared`, 16 characters: the column grows to 20). Widening
 * a varchar rewrites no row and the trigger acts on rows, not on DDL, so the marks stay insert-only: only the list of known
 * methods grows.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE attendance_marks
                ALTER COLUMN method TYPE varchar(20),
                DROP CONSTRAINT attendance_marks_method_known,
                ADD CONSTRAINT attendance_marks_method_known CHECK (method IN ('qr_and_geofence', 'geofence', 'qr', 'remote', 'trusted_browser', 'correction', 'field', 'field_unlocated', 'offline_qr', 'offline_declared'));
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE attendance_marks
                DROP CONSTRAINT attendance_marks_method_known,
                ADD CONSTRAINT attendance_marks_method_known CHECK (method IN ('qr_and_geofence', 'geofence', 'qr', 'remote', 'trusted_browser', 'correction', 'field', 'field_unlocated')),
                ALTER COLUMN method TYPE varchar(15);
        SQL);
    }
};
