<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each work center measures its working day in its own IANA zone, and the company keeps one for what is not of a center
 * (design: docs/superpowers/specs/2026-11-17-timezones-design.md). Only the zones the system lists for Mexico, as
 * MexicanTimezones allows them. With no customers yet, what exists stays in Mexico City, the zone used until now.
 */
return new class extends Migration
{
    public function up(): void
    {
        $zones = implode(', ', array_map(fn (string $zone): string => "'{$zone}'", DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, 'MX')));

        foreach (['tenants', 'work_centers'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('timezone', 40)->default('America/Mexico_City');
            });

            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_timezone_known CHECK (timezone IN ({$zones}))");
        }
    }

    public function down(): void
    {
        foreach (['tenants', 'work_centers'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('timezone');
            });
        }
    }
};
