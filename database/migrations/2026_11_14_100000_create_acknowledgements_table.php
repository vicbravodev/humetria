<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The acknowledgements («acuses») of published NOM-037 documents (criterios C-20, C-23): a request row per document
 * version and person, filled when the person confirms. The person is an employee (portal or paper) or a user (a
 * Comisión member, in the panel), never both. Rows are never deleted: a new version asks again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acknowledgements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('document_id');
            $table->uuid('employee_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('channel', 10)->nullable();
            $table->timestampTz('requested_at');
            $table->timestampTz('acknowledged_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->foreign(['document_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('telework_documents')->restrictOnDelete();
            $table->foreign(['employee_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('employees')->restrictOnDelete();
            $table->unique(['document_id', 'employee_id'], 'acknowledgements_document_employee_unique');
            $table->unique(['document_id', 'user_id'], 'acknowledgements_document_user_unique');
            $table->index(['tenant_id', 'employee_id']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE acknowledgements
                ADD CONSTRAINT acknowledgements_one_person CHECK ((employee_id IS NULL) <> (user_id IS NULL)),
                ADD CONSTRAINT acknowledgements_channel_known CHECK (channel IS NULL OR channel IN ('portal', 'manual', 'panel')),
                ADD CONSTRAINT acknowledgements_channel_when_acknowledged CHECK ((channel IS NULL) = (acknowledged_at IS NULL))
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('acknowledgements');
    }
};
