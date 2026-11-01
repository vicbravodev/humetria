<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Comisión de seguridad e higiene of a work center (criterio C-17) and its checklist (C-07): the committee, who sits
 * on it, and the versioned list of questions, which starts as a draft copied from the base list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('safety_committees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('work_center_id');
            $table->date('constituted_on');
            $table->string('act_path')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->foreign(['work_center_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('work_centers')->restrictOnDelete();
            $table->unique('work_center_id', 'safety_committees_work_center_unique');
            $table->unique(['id', 'tenant_id'], 'safety_committees_id_tenant_unique');
        });

        Schema::create('committee_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('work_center_id');
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->foreign(['work_center_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('work_centers')->restrictOnDelete();
            $table->unique(['work_center_id', 'user_id'], 'committee_assignments_unique');
            $table->index(['tenant_id', 'user_id']);
            $table->unique(['id', 'tenant_id'], 'committee_assignments_id_tenant_unique');
        });

        Schema::create('checklists', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('work_center_id');
            $table->unsignedSmallInteger('version');
            $table->string('status', 10);
            $table->timestampTz('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->foreign(['work_center_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('work_centers')->restrictOnDelete();
            $table->unique(['work_center_id', 'version'], 'checklists_version_unique');
            $table->unique(['id', 'tenant_id'], 'checklists_id_tenant_unique');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE checklists
                ADD CONSTRAINT checklists_version_positive CHECK (version >= 1),
                ADD CONSTRAINT checklists_status_known CHECK (status IN ('draft', 'published')),
                ADD CONSTRAINT checklists_published_dated CHECK ((status = 'published') = (published_at IS NOT NULL))
        SQL);
        // One draft at a time per center: the committee edits it, then publishes it.
        DB::statement("CREATE UNIQUE INDEX checklists_one_draft ON checklists (work_center_id) WHERE status = 'draft'");

        Schema::create('checklist_questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('checklist_id');
            $table->unsignedSmallInteger('section');
            $table->unsignedSmallInteger('position');
            $table->string('code', 20);
            $table->string('heading');
            $table->text('text');
            $table->string('origin', 15);
            $table->string('favorable', 11);
            $table->string('requirement', 10)->nullable();
            $table->boolean('sensitive')->default(false);
            $table->string('base_code', 20)->nullable();
            $table->timestampsTz();

            $table->foreign(['checklist_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('checklists')->restrictOnDelete();
            $table->unique(['checklist_id', 'code'], 'checklist_questions_code_unique');
            $table->unique(['checklist_id', 'position'], 'checklist_questions_position_unique');
            $table->unique(['id', 'tenant_id'], 'checklist_questions_id_tenant_unique');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE checklist_questions
                ADD CONSTRAINT checklist_questions_section_known CHECK (section IN (1, 2)),
                ADD CONSTRAINT checklist_questions_position_positive CHECK (position >= 1),
                ADD CONSTRAINT checklist_questions_origin_known CHECK (origin IN ('dof', 'dof_modified', 'humetria', 'committee')),
                ADD CONSTRAINT checklist_questions_favorable_known CHECK (favorable IN ('yes', 'no', 'informative')),
                ADD CONSTRAINT checklist_questions_modified_has_base CHECK (origin <> 'dof_modified' OR base_code IS NOT NULL)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_questions');
        Schema::dropIfExists('checklists');
        Schema::dropIfExists('committee_assignments');
        Schema::dropIfExists('safety_committees');
    }
};
