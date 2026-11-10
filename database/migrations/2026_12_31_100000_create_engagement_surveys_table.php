<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TEMPORARY, from the Task 12 stream: the minimum the response tables need to point at (the shape of Task 5, to the
 * letter). When Task 5 lands, its migration replaces this file.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engagement_surveys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->string('kind', 10);
            $table->string('status', 10)->default('draft');
            $table->string('name', 120);
            $table->string('bank_version', 10);
            $table->jsonb('items');
            $table->date('closes_on')->nullable();
            $table->timestampTz('launched_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->unsignedInteger('invited')->nullable();
            $table->jsonb('priorities')->nullable();
            $table->boolean('sensitive_mentions')->default(false);
            $table->timestampTz('results_published_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->unique(['id', 'tenant_id'], 'engagement_surveys_id_tenant_unique');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE engagement_surveys
                ADD CONSTRAINT engagement_surveys_kind_known CHECK (kind IN ('climate', 'pulse')),
                ADD CONSTRAINT engagement_surveys_status_known CHECK (status IN ('draft', 'active', 'closed')),
                ADD CONSTRAINT engagement_surveys_launched_dated CHECK (status = 'draft' OR (launched_at IS NOT NULL AND closes_on IS NOT NULL)),
                ADD CONSTRAINT engagement_surveys_closed_dated CHECK ((status = 'closed') = (closed_at IS NOT NULL))
        SQL);
        DB::statement("CREATE UNIQUE INDEX engagement_surveys_one_open ON engagement_surveys (tenant_id, kind) WHERE status IN ('draft', 'active')");
    }

    public function down(): void
    {
        Schema::dropIfExists('engagement_surveys');
    }
};
