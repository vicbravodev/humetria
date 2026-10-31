<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Every topic of a training is one of the eight of numeral 8.1 (a CHECK cannot hold a subquery, so containment does it).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE trainings
                ADD CONSTRAINT trainings_topics_known CHECK (topics <@ '["hazards", "ergonomic_postures", "ergonomic_repetitive", "ergonomic_sitting", "psychosocial", "policy_understanding", "policy_application", "ict"]'::jsonb)
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE trainings DROP CONSTRAINT trainings_topics_known');
    }
};
