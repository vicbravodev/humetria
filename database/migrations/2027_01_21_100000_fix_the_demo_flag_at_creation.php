<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Whether a company is the demo is decided when its row is inserted, and never changes: the demo's rows may be deleted by
 * the reset (2027_01_19), so flipping a real company into the demo would expose its evidence, and flipping the demo out
 * would leave a reset unable to wipe it.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION tenants_is_demo_is_fixed() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'tenants.is_demo is fixed at creation';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER tenants_is_demo_is_fixed
                BEFORE UPDATE OF is_demo ON tenants
                FOR EACH ROW WHEN (OLD.is_demo IS DISTINCT FROM NEW.is_demo)
                EXECUTE FUNCTION tenants_is_demo_is_fixed();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER tenants_is_demo_is_fixed ON tenants;
            DROP FUNCTION tenants_is_demo_is_fixed();
            SQL);
    }
};
