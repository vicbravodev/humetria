<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The answers of Clima y pulso, insert-only and with no way back to the person: no employee, invitation or token, and no
 * date or time (the order or the moment would tell). `manager_group_id` and `comment_group_id` name the group of a
 * manager, not who answered, so they carry no foreign key and the tables never reference `employees`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engagement_responses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('survey_id');
            $table->uuid('work_center_id')->nullable();
            $table->uuid('area_id')->nullable();
            $table->string('tenure_band', 10)->nullable();
            $table->uuid('manager_group_id')->nullable();
            $table->jsonb('answers');
            $table->smallInteger('minutes')->nullable();

            $table->foreign(['survey_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('engagement_surveys')->restrictOnDelete();
            $table->foreign(['work_center_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('work_centers')->restrictOnDelete();
            $table->foreign(['area_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('areas')->restrictOnDelete();
            $table->index(['tenant_id', 'survey_id']);
        });

        DB::statement('ALTER TABLE engagement_responses ADD CONSTRAINT engagement_responses_minutes_plausible CHECK (minutes IS NULL OR minutes BETWEEN 1 AND 60)');

        Schema::create('engagement_comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('survey_id');
            $table->string('item_key', 60);
            $table->uuid('comment_group_id')->nullable();
            $table->text('body');

            $table->foreign(['survey_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('engagement_surveys')->restrictOnDelete();
            $table->index(['tenant_id', 'survey_id']);
        });

        foreach (['engagement_responses', 'engagement_comments'] as $table) {
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
        Schema::dropIfExists('engagement_comments');
        Schema::dropIfExists('engagement_responses');

        foreach (['engagement_responses', 'engagement_comments'] as $table) {
            DB::unprepared("DROP FUNCTION IF EXISTS {$table}_insert_only()");
        }
    }
};
