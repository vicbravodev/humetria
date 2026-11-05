<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * NOM-037: a document being written with questions (rediseño v2, boards V2-Politica and V3-Doc-Riesgos; decisión A3). One
 * per company and kind, it keeps every answer, the paragraphs RH rewrote and the question it was on, so the writing can be
 * left and resumed (an empty map is stored as []). Publishing makes a version in telework_documents; the draft stays as the start of the next one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telework_document_drafts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->string('kind', 30);
            $table->jsonb('answers');
            $table->jsonb('edits');
            $table->unsignedSmallInteger('step');
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'kind'], 'telework_document_drafts_kind_unique');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE telework_document_drafts
                ADD CONSTRAINT telework_document_drafts_kind_known CHECK (kind IN ('policy', 'risk_information', 'implementation_process', 'maintenance_program', 'reversibility', 'family_violence', 'committee_participation')),
                ADD CONSTRAINT telework_document_drafts_answers_object CHECK (jsonb_typeof(answers) = 'object' OR answers = '[]'::jsonb),
                ADD CONSTRAINT telework_document_drafts_edits_object CHECK (jsonb_typeof(edits) = 'object' OR edits = '[]'::jsonb)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('telework_document_drafts');
    }
};
