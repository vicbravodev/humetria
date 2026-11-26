<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Catalog v2 has no Enterprise plan (single sign-on went to Integral): the database stops taking it.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE subscriptions DROP CONSTRAINT subscriptions_plan_known');
        DB::statement("ALTER TABLE subscriptions ADD CONSTRAINT subscriptions_plan_known CHECK (plan IN ('jornada', 'cumplimiento', 'integral', 'sindicato'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE subscriptions DROP CONSTRAINT subscriptions_plan_known');
        DB::statement("ALTER TABLE subscriptions ADD CONSTRAINT subscriptions_plan_known CHECK (plan IN ('jornada', 'cumplimiento', 'integral', 'enterprise', 'sindicato'))");
    }
};
