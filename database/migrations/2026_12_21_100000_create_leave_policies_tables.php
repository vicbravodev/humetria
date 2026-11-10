<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vacaciones: the policies of a company and their rows (criterios C-08, C-13 and C-14). The law is the floor in the
 * database too: no tier may give fewer days than leave_statutory_days() asks for its last year, and no premium is under
 * 25 percent. The premium is a percentage, never an amount. The «Ley» policy is not a row: a null policy means the law.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The table of LFT art. 76 in force (criterio C-01), the same as App\Modules\Leave\Support\StatutoryDays::current().
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION leave_statutory_days(years integer) RETURNS integer AS $$
                SELECT CASE WHEN years <= 5 THEN 10 + 2 * years ELSE 20 + 2 * ((years - 1) / 5) END;
            $$ LANGUAGE sql IMMUTABLE;
        SQL);

        Schema::create('leave_policies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->smallInteger('premium_percent');
            $table->string('work_week', 7);
            $table->boolean('allows_advance')->default(false);
            $table->smallInteger('extra_expires_months')->nullable();
            $table->smallInteger('extra_carryover_cap_days')->nullable();
            $table->timestampTz('archived_at')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'name']);
            $table->unique(['id', 'tenant_id'], 'leave_policies_id_tenant_unique');
        });

        Schema::create('leave_policy_rows', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('leave_policy_id');
            $table->string('kind', 5);
            $table->string('label', 120)->nullable();
            $table->smallInteger('year_from')->nullable();
            $table->smallInteger('year_to')->nullable();
            $table->smallInteger('days');
            $table->timestampsTz();

            $table->foreign(['leave_policy_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('leave_policies')->restrictOnDelete();
            $table->index(['tenant_id', 'leave_policy_id']);
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE leave_policies
                ADD CONSTRAINT leave_policies_premium_floor CHECK (premium_percent >= 25),
                ADD CONSTRAINT leave_policies_work_week_known CHECK (work_week IN ('mon_fri', 'mon_sat')),
                ADD CONSTRAINT leave_policies_expiry_positive CHECK (extra_expires_months > 0),
                ADD CONSTRAINT leave_policies_cap_not_negative CHECK (extra_carryover_cap_days >= 0);

            ALTER TABLE leave_policy_rows
                ADD CONSTRAINT leave_policy_rows_shape CHECK (
                    (kind = 'tier' AND year_from IS NOT NULL AND year_to IS NOT NULL AND year_from >= 1 AND year_to >= year_from
                        AND days >= leave_statutory_days(year_to))
                    OR (kind = 'fixed' AND year_from IS NULL AND year_to IS NULL AND days > 0
                        AND length(btrim(coalesce(label, ''))) > 0)
                );
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_policy_rows');
        Schema::dropIfExists('leave_policies');
        DB::unprepared('DROP FUNCTION IF EXISTS leave_statutory_days(integer)');
    }
};
