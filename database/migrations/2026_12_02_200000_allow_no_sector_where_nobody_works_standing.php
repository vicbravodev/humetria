<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ley Silla: a center that declares that nobody works standing needs no sector (rediseño v2, boards V2-Aplica-Otras and
 * V3-Onboarding); wherever someone does, the sector is still required.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE seating_profiles ALTER COLUMN sector DROP NOT NULL');
        DB::statement('ALTER TABLE seating_profiles ADD CONSTRAINT seating_profiles_sector_when_standing CHECK (sector IS NOT NULL OR no_standing_positions)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE seating_profiles DROP CONSTRAINT seating_profiles_sector_when_standing');
        DB::statement("UPDATE seating_profiles SET sector = 'services' WHERE sector IS NULL");
        DB::statement('ALTER TABLE seating_profiles ALTER COLUMN sector SET NOT NULL');
    }
};
