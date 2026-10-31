<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What a person answered in one checklist application. An answer is null only on a sensitive question left
        // unanswered because the consent was declined; a risk note needs an answer (criterios C-10 and C-25).
        Schema::create('checklist_answers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('application_id');
            $table->uuid('question_id');
            $table->string('answer', 14)->nullable();
            $table->text('risk_note')->nullable();
            $table->timestampsTz();

            $table->foreign(['application_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('checklist_applications')->restrictOnDelete();
            $table->foreign(['question_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('checklist_questions')->restrictOnDelete();
            $table->unique(['application_id', 'question_id']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE checklist_answers
                ADD CONSTRAINT checklist_answers_answer_known CHECK (answer IN ('yes', 'no', 'not_applicable')),
                ADD CONSTRAINT checklist_answers_note_needs_answer CHECK (risk_note IS NULL OR answer IS NOT NULL),
                ADD CONSTRAINT checklist_answers_note_not_blank CHECK (risk_note IS NULL OR btrim(risk_note) <> '')
        SQL);

        // Photos of the workplace: of one question, or of the whole list when `question_id` is null.
        Schema::create('checklist_photos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('application_id');
            $table->uuid('question_id')->nullable();
            $table->string('path');
            $table->timestampTz('uploaded_at');
            $table->timestampsTz();

            $table->foreign(['application_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('checklist_applications')->restrictOnDelete();
            $table->foreign(['question_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('checklist_questions')->restrictOnDelete();
            $table->index('application_id');
            $table->unique('path');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_photos');
        Schema::dropIfExists('checklist_answers');
    }
};
