<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ley Silla: a campaign of a new kind, the confidential survey of a work center's seats and rests, answered by everyone
 * in the center with no sample. A center has at most one open at a time.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE campaigns DROP CONSTRAINT campaigns_kind_known');
        DB::statement("ALTER TABLE campaigns ADD CONSTRAINT campaigns_kind_known CHECK (kind IN ('nom035_evaluation', 'nom035_trauma', 'seating_survey'))");
        DB::statement("CREATE UNIQUE INDEX campaigns_one_open_seating_survey ON campaigns (tenant_id, work_center_id) WHERE kind = 'seating_survey' AND status IN ('draft', 'active')");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS campaigns_one_open_seating_survey');
        DB::statement("DELETE FROM campaigns WHERE kind = 'seating_survey'");
        DB::statement('ALTER TABLE campaigns DROP CONSTRAINT campaigns_kind_known');
        DB::statement("ALTER TABLE campaigns ADD CONSTRAINT campaigns_kind_known CHECK (kind IN ('nom035_evaluation', 'nom035_trauma'))");
    }
};
