<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ley Silla: the individual risk assessment (Quinta C and D, criterio C-07). A draft can change; a signed one is evidence
 * and is final (criterio C-17): the database refuses to change or delete it, except the two marks that are not part of
 * the evidence (`reassessment_due` and `recorded_in_document_id`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_assessments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('employee_id');
            $table->uuid('position_id');
            $table->uuid('work_center_id');
            $table->jsonb('scores')->default('{}');
            $table->unsignedSmallInteger('total')->nullable();
            $table->string('level', 6)->nullable();
            $table->string('instrument_version', 20)->nullable();
            $table->string('status', 6);
            $table->date('verified_on')->nullable();
            $table->text('other_aspects')->nullable();
            $table->jsonb('snapshot')->nullable();
            $table->boolean('person_provided_health_data');
            // The commission's minutes where it was recorded (Quinta B); the document table arrives with the documents.
            $table->uuid('recorded_in_document_id')->nullable();
            $table->boolean('reassessment_due')->default(false);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('signed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('signed_at')->nullable();
            $table->timestampsTz();

            $table->foreign(['employee_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('employees')->restrictOnDelete();
            $table->foreign(['position_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('standing_positions')->restrictOnDelete();
            $table->foreign(['work_center_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('work_centers')->restrictOnDelete();
            $table->index(['employee_id', 'signed_at']);
            $table->index(['work_center_id', 'status']);
            $table->unique(['id', 'tenant_id'], 'risk_assessments_id_tenant_unique');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE risk_assessments
                ADD CONSTRAINT risk_assessments_status_known CHECK (status IN ('draft', 'signed')),
                ADD CONSTRAINT risk_assessments_total_range CHECK (total IS NULL OR total BETWEEN 7 AND 21),
                ADD CONSTRAINT risk_assessments_level_known CHECK (level IS NULL OR level IN ('low', 'medium', 'high')),
                ADD CONSTRAINT risk_assessments_signed_is_complete CHECK ((status = 'signed') = (
                    signed_at IS NOT NULL AND signed_by IS NOT NULL AND total IS NOT NULL AND level IS NOT NULL
                    AND instrument_version IS NOT NULL AND snapshot IS NOT NULL
                ))
        SQL);
        // One draft at a time per person; the signed ones are the history.
        DB::statement("CREATE UNIQUE INDEX risk_assessments_one_draft ON risk_assessments (employee_id) WHERE status = 'draft'");

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION risk_assessments_signed_is_final() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF OLD.status = 'signed' AND (TG_OP = 'DELETE' OR
                    (to_jsonb(NEW) - 'reassessment_due' - 'recorded_in_document_id' - 'updated_at')
                    IS DISTINCT FROM (to_jsonb(OLD) - 'reassessment_due' - 'recorded_in_document_id' - 'updated_at')) THEN
                    RAISE EXCEPTION 'a signed assessment is final';
                END IF;

                RETURN CASE WHEN TG_OP = 'DELETE' THEN OLD ELSE NEW END;
            END;
            $$
        SQL);
        DB::statement('CREATE TRIGGER risk_assessments_signed_is_final BEFORE UPDATE OR DELETE ON risk_assessments FOR EACH ROW EXECUTE FUNCTION risk_assessments_signed_is_final()');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS risk_assessments_signed_is_final ON risk_assessments');
        DB::statement('DROP FUNCTION IF EXISTS risk_assessments_signed_is_final()');
        Schema::dropIfExists('risk_assessments');
    }
};
