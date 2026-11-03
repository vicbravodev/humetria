<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ley Silla: the action plan (criterio C-10): preventive measures of Quinta G with who answers for them, by when and with
 * what evidence, and the agreements on pauses reached with the people (criterio C-14). Neither is deleted (C-17).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preventive_measures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('work_center_id');
            $table->uuid('assessment_id')->nullable();
            $table->uuid('position_id')->nullable();
            $table->string('kind', 20);
            $table->text('note')->nullable();
            $table->string('status', 11);
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->date('due_on')->nullable();
            $table->string('evidence_path')->nullable();
            $table->foreignId('proposed_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->foreign(['work_center_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('work_centers')->restrictOnDelete();
            $table->foreign(['assessment_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('risk_assessments')->restrictOnDelete();
            $table->foreign(['position_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('standing_positions')->restrictOnDelete();
            $table->index(['work_center_id', 'status']);
            $table->index('assessment_id');
            $table->unique(['id', 'tenant_id'], 'preventive_measures_id_tenant_unique');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE preventive_measures
                ADD CONSTRAINT preventive_measures_kind_known CHECK (kind IN ('workstation_design', 'task_rotation', 'ergonomic_footwear', 'cushioned_floor', 'active_breaks', 'other')),
                ADD CONSTRAINT preventive_measures_status_known CHECK (status IN ('planned', 'in_progress', 'done', 'cancelled')),
                ADD CONSTRAINT preventive_measures_tied_to_something CHECK (assessment_id IS NOT NULL OR position_id IS NOT NULL),
                ADD CONSTRAINT preventive_measures_other_has_note CHECK (kind <> 'other' OR length(btrim(coalesce(note, ''))) > 0)
        SQL);

        Schema::create('pause_agreements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('work_center_id');
            $table->uuid('position_id');
            $table->unsignedSmallInteger('pauses_per_shift');
            $table->unsignedSmallInteger('minutes_each');
            $table->date('agreed_on');
            $table->text('how_agreed');
            $table->string('evidence_path')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->foreign(['work_center_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('work_centers')->restrictOnDelete();
            $table->foreign(['position_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('standing_positions')->restrictOnDelete();
            $table->index(['position_id', 'agreed_on']);
            $table->unique(['id', 'tenant_id'], 'pause_agreements_id_tenant_unique');
        });

        // The law sets no number of pauses and no minimum of minutes (criterio C-14): only that there are some.
        DB::statement(<<<'SQL'
            ALTER TABLE pause_agreements
                ADD CONSTRAINT pause_agreements_pauses_positive CHECK (pauses_per_shift >= 1),
                ADD CONSTRAINT pause_agreements_minutes_positive CHECK (minutes_each >= 1)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('pause_agreements');
        Schema::dropIfExists('preventive_measures');
    }
};
