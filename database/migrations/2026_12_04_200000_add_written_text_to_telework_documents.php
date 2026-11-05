<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * NOM-037: a version of a document is a PDF the company uploads or a text written with questions (rediseño v2, decisión
 * A3), never both: the text is kept as its sections, with the version of the template it was written with.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE telework_documents
                ALTER COLUMN path DROP NOT NULL,
                ADD COLUMN body jsonb NULL,
                ADD COLUMN template_version varchar(40) NULL,
                ADD CONSTRAINT telework_documents_file_or_text CHECK ((path IS NULL) <> (body IS NULL)),
                ADD CONSTRAINT telework_documents_text_versioned CHECK ((body IS NULL) = (template_version IS NULL)),
                ADD CONSTRAINT telework_documents_body_array CHECK (body IS NULL OR jsonb_typeof(body) = 'array')
        SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE telework_documents
                DROP CONSTRAINT telework_documents_body_array,
                DROP CONSTRAINT telework_documents_text_versioned,
                DROP CONSTRAINT telework_documents_file_or_text,
                DROP COLUMN template_version,
                DROP COLUMN body,
                ALTER COLUMN path SET NOT NULL
        SQL);
    }
};
