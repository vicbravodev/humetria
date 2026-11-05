<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * NOM-035: the policy and the blocks of 5.7 written with questions (rediseño v2, boards V2-N035-Politica and
 * V4-Difusion-Contenidos; decisión A3). A draft keeps every answer, the paragraphs RH rewrote and the question it was on, so
 * the writing can be left and resumed; its `content` is the text they make. A version published from it keeps them as they
 * were, and the next draft starts from them. Null for a text written before (by hand).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disclosure_documents', function (Blueprint $table) {
            $table->jsonb('answers')->nullable();
            $table->jsonb('edits')->nullable();
            $table->unsignedSmallInteger('step')->nullable();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE disclosure_documents
                ADD CONSTRAINT disclosure_documents_answers_object CHECK (answers IS NULL OR jsonb_typeof(answers) = 'object' OR answers = '[]'::jsonb),
                ADD CONSTRAINT disclosure_documents_edits_object CHECK (edits IS NULL OR jsonb_typeof(edits) = 'object' OR edits = '[]'::jsonb),
                ADD CONSTRAINT disclosure_documents_written_together CHECK ((answers IS NULL) = (edits IS NULL) AND (answers IS NULL) = (step IS NULL))
        SQL);
    }

    public function down(): void
    {
        Schema::table('disclosure_documents', function (Blueprint $table) {
            $table->dropColumn(['answers', 'edits', 'step']);
        });
    }
};
