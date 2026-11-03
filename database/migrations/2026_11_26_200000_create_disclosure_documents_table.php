<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The texts a company tells its people (NOM-035 5.1 and 5.7; criterio C-50): the policy of prevention and the texts of the
 * blocks 5.7 b, c, d and f. A version of a kind is a draft (no number yet, one per company and kind) until it is published;
 * a published one is evidence (num. 10.4) and never changes (a trigger, in its own migration).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disclosure_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->string('kind', 30);
            $table->unsignedSmallInteger('version')->nullable();
            $table->string('status', 10);
            $table->jsonb('content');
            $table->string('template_version', 30)->nullable();
            $table->jsonb('covers')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'kind', 'version'], 'disclosure_documents_version_unique');
            $table->unique(['id', 'tenant_id'], 'disclosure_documents_id_tenant_unique');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE disclosure_documents
                ADD CONSTRAINT disclosure_documents_kind_known CHECK (kind IN ('policy', 'violence_measures', 'prevention_measures', 'complaint_mechanism', 'health_effects')),
                ADD CONSTRAINT disclosure_documents_status_known CHECK (status IN ('draft', 'published')),
                ADD CONSTRAINT disclosure_documents_version_positive CHECK (version IS NULL OR version >= 1),
                ADD CONSTRAINT disclosure_documents_content_array CHECK (jsonb_typeof(content) = 'array'),
                ADD CONSTRAINT disclosure_documents_covers_of_policy CHECK (covers IS NULL OR (kind = 'policy' AND jsonb_typeof(covers) = 'array')),
                ADD CONSTRAINT disclosure_documents_published_is_complete CHECK ((status = 'published' AND version IS NOT NULL AND published_at IS NOT NULL AND published_by IS NOT NULL) OR (status = 'draft' AND version IS NULL AND published_at IS NULL AND published_by IS NULL))
        SQL);

        // One draft at a time per company and kind.
        DB::statement("CREATE UNIQUE INDEX disclosure_documents_one_draft ON disclosure_documents (tenant_id, kind) WHERE status = 'draft'");
    }

    public function down(): void
    {
        Schema::dropIfExists('disclosure_documents');
    }
};
