<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ley Silla: the documents RH keeps for an inspection (PDF only, on the private disk) and the internal regulation of a
 * center with the steps it goes through: drafted, signed, deposited and distributed (criterio C-13). Nothing is deleted
 * (C-17). It also gives the Comisión's minutes the foreign key the assessments' `recorded_in_document_id` was waiting for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seating_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('work_center_id');
            $table->string('kind', 20);
            $table->string('title', 200);
            $table->date('document_on');
            $table->jsonb('incisos')->default('[]');
            $table->string('file_path');
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->foreign(['work_center_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('work_centers')->restrictOnDelete();
            $table->index(['work_center_id', 'kind', 'document_on']);
            $table->unique(['id', 'tenant_id'], 'seating_documents_id_tenant_unique');
        });

        DB::statement("ALTER TABLE seating_documents ADD CONSTRAINT seating_documents_kind_known CHECK (kind IN ('regulation', 'commission_minutes', 'diagnosis_program', 'other'))");

        Schema::table('risk_assessments', function (Blueprint $table) {
            $table->foreign(['recorded_in_document_id', 'tenant_id'], 'risk_assessments_recorded_in_document_fk')->references(['id', 'tenant_id'])->on('seating_documents')->restrictOnDelete();
        });

        Schema::create('internal_regulations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('work_center_id');
            $table->jsonb('parts');
            $table->string('status', 11);
            $table->date('signed_on')->nullable();
            $table->date('deposited_on')->nullable();
            $table->string('deposit_folio', 100)->nullable();
            $table->date('distributed_on')->nullable();
            $table->string('file_path')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->foreign(['work_center_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('work_centers')->restrictOnDelete();
            $table->unique('work_center_id', 'internal_regulations_work_center_unique');
            $table->unique(['id', 'tenant_id'], 'internal_regulations_id_tenant_unique');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE internal_regulations
                ADD CONSTRAINT internal_regulations_status_known CHECK (status IN ('draft', 'signed', 'deposited', 'distributed')),
                ADD CONSTRAINT internal_regulations_status_has_its_data CHECK (
                    (status = 'draft' OR (signed_on IS NOT NULL AND file_path IS NOT NULL))
                    AND (status IN ('draft', 'signed') OR (deposited_on IS NOT NULL AND deposit_folio IS NOT NULL))
                    AND (status <> 'distributed' OR distributed_on IS NOT NULL)
                )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_regulations');
        Schema::table('risk_assessments', function (Blueprint $table) {
            $table->dropForeign('risk_assessments_recorded_in_document_fk');
        });
        Schema::dropIfExists('seating_documents');
    }
};
