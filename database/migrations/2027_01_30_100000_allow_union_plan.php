<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The plan of a union: «Sindicato», paid with its flat fee ('union') or a negotiated price. The database keeps the pair
 * honest: the flat fee only goes with that plan, and that plan only with the flat fee or a negotiated price.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE subscriptions DROP CONSTRAINT subscriptions_plan_known');
        DB::statement('ALTER TABLE subscriptions DROP CONSTRAINT subscriptions_profile_known');
        DB::statement("ALTER TABLE subscriptions ADD CONSTRAINT subscriptions_plan_known CHECK (plan IN ('jornada', 'cumplimiento', 'integral', 'enterprise', 'sindicato'))");
        DB::statement("ALTER TABLE subscriptions ADD CONSTRAINT subscriptions_profile_known CHECK (profile IN ('direct', 'channel_compliance', 'channel_integral', 'negotiated', 'union'))");
        DB::statement("ALTER TABLE subscriptions ADD CONSTRAINT subscriptions_union_fee_only_on_union_plan CHECK (profile <> 'union' OR plan = 'sindicato')");
        DB::statement("ALTER TABLE subscriptions ADD CONSTRAINT subscriptions_union_plan_only_flat_or_negotiated CHECK (plan <> 'sindicato' OR profile IN ('union', 'negotiated'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE subscriptions DROP CONSTRAINT subscriptions_union_plan_only_flat_or_negotiated');
        DB::statement('ALTER TABLE subscriptions DROP CONSTRAINT subscriptions_union_fee_only_on_union_plan');
        DB::statement('ALTER TABLE subscriptions DROP CONSTRAINT subscriptions_profile_known');
        DB::statement('ALTER TABLE subscriptions DROP CONSTRAINT subscriptions_plan_known');
        DB::statement("ALTER TABLE subscriptions ADD CONSTRAINT subscriptions_plan_known CHECK (plan IN ('jornada', 'cumplimiento', 'integral', 'enterprise'))");
        DB::statement("ALTER TABLE subscriptions ADD CONSTRAINT subscriptions_profile_known CHECK (profile IN ('direct', 'channel_compliance', 'channel_integral', 'negotiated'))");
    }
};
