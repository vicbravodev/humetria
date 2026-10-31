<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The NOM-037 documents of a work center (criterios C-19, C-21, C-23): one row per center and version, several rows may
 * share a file. Never updated or deleted: a new upload is a new version.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telework_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('work_center_id');
            $table->string('kind', 30);
            $table->unsignedSmallInteger('version');
            $table->string('path');
            $table->jsonb('covered_items');
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->foreign(['work_center_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('work_centers')->restrictOnDelete();
            $table->unique(['work_center_id', 'kind', 'version'], 'telework_documents_version_unique');
            $table->unique(['id', 'tenant_id'], 'telework_documents_id_tenant_unique');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE telework_documents
                ADD CONSTRAINT telework_documents_version_positive CHECK (version >= 1),
                ADD CONSTRAINT telework_documents_kind_known CHECK (kind IN ('policy', 'risk_information', 'implementation_process', 'maintenance_program', 'reversibility', 'family_violence', 'committee_participation', 'inspection_report')),
                ADD CONSTRAINT telework_documents_items_array CHECK (jsonb_typeof(covered_items) = 'array')
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('telework_documents');
    }
};
