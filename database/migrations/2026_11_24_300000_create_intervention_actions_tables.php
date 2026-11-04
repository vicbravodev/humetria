<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The actions of a Programa de intervención (num. 8.4 and 8.5) and the areas they are for (criterio C-33). An action is for
 * the whole work center or for areas shown by Results, never for people; the third level (clinical or therapeutic care) is
 * declared as given by a physician, psychologist or psychiatrist (8.5, last paragraph; criterio C-32). An action is
 * cancelled with a reason, never deleted (criterio C-35); each step it takes is a row of the progress log (8.4 d).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intervention_actions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('program_id');
            $table->uuid('item_id');
            $table->unsignedSmallInteger('level');
            $table->string('kind', 24);
            $table->string('title', 160);
            $table->text('description');
            $table->string('subjects', 6);
            $table->date('starts_on')->nullable();
            $table->date('due_on');
            $table->string('responsible_name', 120);
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('professional', 12)->nullable();
            $table->string('status', 11);
            $table->date('completed_on')->nullable();
            $table->text('cancelled_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->foreign(['program_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('intervention_programs')->restrictOnDelete();
            $table->foreign(['item_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('intervention_items')->restrictOnDelete();
            $table->index(['program_id', 'status']);
            $table->index(['item_id']);
            $table->unique(['id', 'tenant_id'], 'intervention_actions_id_tenant_unique');
        });

        $kinds = implode(', ', array_map(fn (string $kind): string => "'{$kind}'", ['policy_review', 'policy_dissemination', 'work_organization', 'violence_prevention', 'awareness_campaign', 'training', 'social_support', 'specific_assessment', 'clinical_care', 'other']));

        DB::statement(<<<SQL
            ALTER TABLE intervention_actions
                ADD CONSTRAINT intervention_actions_level_known CHECK (level IN (1, 2, 3)),
                ADD CONSTRAINT intervention_actions_kind_known CHECK (kind IN ({$kinds})),
                ADD CONSTRAINT intervention_actions_subjects_known CHECK (subjects IN ('center', 'areas')),
                ADD CONSTRAINT intervention_actions_status_known CHECK (status IN ('planned', 'in_progress', 'done', 'cancelled')),
                ADD CONSTRAINT intervention_actions_professional_known CHECK (professional IS NULL OR professional IN ('physician', 'psychologist', 'psychiatrist')),
                ADD CONSTRAINT intervention_actions_clinical_is_third_level CHECK ((kind = 'clinical_care') = (level = 3)),
                ADD CONSTRAINT intervention_actions_third_level_has_professional CHECK ((level = 3) = (professional IS NOT NULL)),
                ADD CONSTRAINT intervention_actions_due_not_before_start CHECK (starts_on IS NULL OR due_on >= starts_on),
                ADD CONSTRAINT intervention_actions_done_is_dated CHECK ((status = 'done') = (completed_on IS NOT NULL)),
                ADD CONSTRAINT intervention_actions_cancelled_has_reason CHECK (status <> 'cancelled' OR length(btrim(coalesce(cancelled_reason, ''))) > 0),
                ADD CONSTRAINT intervention_actions_title_present CHECK (length(btrim(title)) > 0),
                ADD CONSTRAINT intervention_actions_responsible_present CHECK (length(btrim(responsible_name)) > 0)
        SQL);

        Schema::create('intervention_action_areas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('action_id');
            $table->uuid('area_id');
            $table->timestampsTz();

            $table->foreign(['action_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('intervention_actions')->restrictOnDelete();
            $table->foreign(['area_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('areas')->restrictOnDelete();
            $table->unique(['action_id', 'area_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intervention_action_areas');
        Schema::dropIfExists('intervention_actions');
    }
};
