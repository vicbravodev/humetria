<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The work centers a published version reached (criterio C-50): written once, when it is published. The version in force
 * of a kind in a center is the highest one that reached it. A published version and its centers are evidence (num. 10.4):
 * a trigger refuses to change or delete them, so no code has to remember not to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disclosure_document_centers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('document_id');
            $table->uuid('work_center_id');
            $table->timestampsTz();

            $table->foreign(['document_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('disclosure_documents')->restrictOnDelete();
            $table->foreign(['work_center_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('work_centers')->restrictOnDelete();
            $table->unique(['document_id', 'work_center_id'], 'disclosure_document_centers_unique');
            $table->index(['tenant_id', 'work_center_id']);
        });

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION disclosure_published_is_final() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_TABLE_NAME = 'disclosure_document_centers' OR OLD.status = 'published' THEN
                    RAISE EXCEPTION 'a published version is final';
                END IF;

                RETURN CASE WHEN TG_OP = 'DELETE' THEN OLD ELSE NEW END;
            END;
            $$
        SQL);
        DB::statement('CREATE TRIGGER disclosure_documents_published_is_final BEFORE UPDATE OR DELETE ON disclosure_documents FOR EACH ROW EXECUTE FUNCTION disclosure_published_is_final()');
        DB::statement('CREATE TRIGGER disclosure_document_centers_are_final BEFORE UPDATE OR DELETE ON disclosure_document_centers FOR EACH ROW EXECUTE FUNCTION disclosure_published_is_final()');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS disclosure_documents_published_is_final ON disclosure_documents');
        Schema::dropIfExists('disclosure_document_centers');
        DB::statement('DROP FUNCTION IF EXISTS disclosure_published_is_final()');
    }
};
