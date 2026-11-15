<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Registro de jornada: a person who holds their own portal link can issue themselves the enrollment link of their phone, so
 * `issued_by` (the RH user who issued it) is null for those. RH still never receives either link.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE attendance_enrollment_links ALTER COLUMN issued_by DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DELETE FROM attendance_enrollment_links WHERE issued_by IS NULL');
        DB::statement('ALTER TABLE attendance_enrollment_links ALTER COLUMN issued_by SET NOT NULL');
    }
};
