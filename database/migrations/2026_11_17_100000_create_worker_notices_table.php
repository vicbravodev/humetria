<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The three written notices of a teleworker (numerals 6.3, 6.7 and 6.9, criterio C-26): a change of conditions, force
 * majeure or severe traumatic event; a change of address; a work risk. Evidence, never edited or deleted once sent;
 * the Comisión's review only fills the three `review` columns, once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worker_notices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('teleworker_id');
            $table->string('kind', 20);
            $table->date('written_on');
            $table->date('occurred_on');
            $table->text('description');
            $table->boolean('traumatic_event')->default(false);
            $table->boolean('temporary')->default(false);
            $table->uuid('new_workplace_id')->nullable();
            $table->timestampTz('submitted_at');
            $table->string('ip', 45)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestampsTz();

            $table->foreign(['teleworker_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('teleworkers')->restrictOnDelete();
            $table->foreign(['new_workplace_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('workplaces')->restrictOnDelete();
            $table->index(['tenant_id', 'teleworker_id']);
            $table->index(['tenant_id', 'reviewed_at', 'submitted_at']);
            $table->index('new_workplace_id');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE worker_notices
                ADD CONSTRAINT worker_notices_kind_known CHECK (kind IN ('condition_change', 'address_change', 'work_risk')),
                ADD CONSTRAINT worker_notices_description_filled CHECK (btrim(description) <> ''),
                ADD CONSTRAINT worker_notices_traumatic_only_conditions CHECK (NOT traumatic_event OR kind = 'condition_change'),
                ADD CONSTRAINT worker_notices_temporary_only_address CHECK (NOT temporary OR kind = 'address_change'),
                ADD CONSTRAINT worker_notices_address_has_workplace CHECK ((kind = 'address_change') = (new_workplace_id IS NOT NULL)),
                ADD CONSTRAINT worker_notices_review_complete CHECK ((reviewed_by IS NULL) = (reviewed_at IS NULL) AND (reviewed_at IS NULL) = (review_note IS NULL))
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_notices');
    }
};
