<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each mark keeps the zone of the place it was taken (design of time zones, §3.4), and the other center of the company
 * where it was taken when that was not the person's own (§3.9, «fuera de su centro»). The marks already there were taken
 * in Mexico City, the only zone until now; from here on the zone has no default: whoever writes a mark says it. The marks
 * stay append-only: adding a column rewrites no row through the trigger.
 */
return new class extends Migration
{
    public function up(): void
    {
        $zones = implode(', ', array_map(fn (string $zone): string => "'{$zone}'", DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, 'MX')));

        Schema::table('attendance_marks', function (Blueprint $table) {
            $table->string('timezone', 40)->default('America/Mexico_City');
            $table->uuid('away_center_id')->nullable();

            $table->foreign(['away_center_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('work_centers')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE attendance_marks ALTER COLUMN timezone DROP DEFAULT');
        DB::statement("ALTER TABLE attendance_marks ADD CONSTRAINT attendance_marks_timezone_known CHECK (timezone IN ({$zones}))");
    }

    public function down(): void
    {
        Schema::table('attendance_marks', function (Blueprint $table) {
            $table->dropForeign(['away_center_id', 'tenant_id']);
            $table->dropColumn(['timezone', 'away_center_id']);
        });
    }
};
