<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Answers, insert-only. Anonymous ones (Guías II and III) keep the campaign, work center and area, and nothing that
 * leads back to the person: no employee, participant, invitation or token, and no timestamps (the order or the moment
 * would tell). Guía I screenings are identified on purpose: the employer must channel the person to care (num. 5.5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anonymous_responses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('campaign_id');
            $table->uuid('work_center_id');
            $table->uuid('area_id');
            $table->string('instrument', 40);
            $table->string('instrument_version', 30);
            $table->jsonb('answers');

            $table->foreign(['campaign_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('campaigns')->restrictOnDelete();
            $table->foreign(['work_center_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('work_centers')->restrictOnDelete();
            $table->foreign(['area_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('areas')->restrictOnDelete();
            $table->index(['tenant_id', 'campaign_id', 'instrument']);
        });

        Schema::create('trauma_screenings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('campaign_id');
            $table->uuid('employee_id');
            $table->string('instrument_version', 30);
            $table->jsonb('answers');
            $table->boolean('requires_clinical_assessment');
            $table->timestampTz('answered_at');

            $table->foreign(['campaign_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('campaigns')->restrictOnDelete();
            $table->foreign(['employee_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('employees')->restrictOnDelete();
            $table->unique(['campaign_id', 'employee_id']);
        });

        foreach (['anonymous_responses', 'trauma_screenings'] as $table) {
            DB::unprepared(<<<SQL
                CREATE OR REPLACE FUNCTION {$table}_insert_only() RETURNS trigger AS \$\$
                BEGIN
                    RAISE EXCEPTION '{$table} is insert-only';
                END;
                \$\$ LANGUAGE plpgsql;

                CREATE TRIGGER {$table}_no_update_delete
                    BEFORE UPDATE OR DELETE ON {$table}
                    FOR EACH ROW EXECUTE FUNCTION {$table}_insert_only();

                CREATE TRIGGER {$table}_no_truncate
                    BEFORE TRUNCATE ON {$table}
                    FOR EACH STATEMENT EXECUTE FUNCTION {$table}_insert_only();
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trauma_screenings');
        Schema::dropIfExists('anonymous_responses');

        foreach (['anonymous_responses', 'trauma_screenings'] as $table) {
            DB::unprepared("DROP FUNCTION IF EXISTS {$table}_insert_only()");
        }
    }
};
