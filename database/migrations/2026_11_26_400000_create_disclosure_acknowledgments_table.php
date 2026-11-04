<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The acknowledgments of the policy (criterio C-53, Humetría's and not the norm's): a request row per version and person,
 * filled when the person confirms they read it, from their link or on paper. No address is kept: confirming a text needs
 * no personal datum beyond the day. Rows are never deleted: a new version asks again and the old rows stay (num. 10.4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disclosure_acknowledgments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('document_id');
            $table->uuid('employee_id');
            $table->string('channel', 10)->nullable();
            $table->timestampTz('requested_at');
            $table->timestampTz('acknowledged_at')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->foreign(['document_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('disclosure_documents')->restrictOnDelete();
            $table->foreign(['employee_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('employees')->restrictOnDelete();
            $table->unique(['document_id', 'employee_id'], 'disclosure_acknowledgments_document_employee_unique');
            $table->index(['tenant_id', 'employee_id']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE disclosure_acknowledgments
                ADD CONSTRAINT disclosure_acknowledgments_channel_known CHECK (channel IS NULL OR channel IN ('portal', 'manual')),
                ADD CONSTRAINT disclosure_acknowledgments_channel_when_acknowledged CHECK ((channel IS NULL) = (acknowledged_at IS NULL)),
                ADD CONSTRAINT disclosure_acknowledgments_manual_only CHECK ((note IS NULL AND recorded_by IS NULL) OR COALESCE(channel = 'manual', false))
        SQL);

        DB::statement("CREATE OR REPLACE FUNCTION disclosure_acknowledgments_are_kept() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN RAISE EXCEPTION 'an acknowledgment is evidence and is never deleted'; END; \$\$");
        DB::statement('CREATE TRIGGER disclosure_acknowledgments_are_kept BEFORE DELETE ON disclosure_acknowledgments FOR EACH ROW EXECUTE FUNCTION disclosure_acknowledgments_are_kept()');
    }

    public function down(): void
    {
        Schema::dropIfExists('disclosure_acknowledgments');
        DB::statement('DROP FUNCTION IF EXISTS disclosure_acknowledgments_are_kept()');
    }
};
