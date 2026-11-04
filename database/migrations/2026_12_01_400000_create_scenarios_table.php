<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A scenario: one proposal to revise the contract. It keeps what the proposal changes (a raise per staff group, in basis
 * points, and new terms for some benefits) and the assumptions of the long-term projection with their source and date, so
 * a later change of the platform's defaults does not move an old scenario. It is never deleted, only archived.
 *
 * The roster, the tabulator and the contract are not copied: a scenario is costed with what they say today.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scenarios', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->date('effective_on');
            $table->unsignedSmallInteger('retroactive_months')->default(0);
            $table->unsignedSmallInteger('horizon_years')->default(3);
            $table->jsonb('group_changes');
            $table->jsonb('term_changes');
            $table->jsonb('assumptions');
            $table->string('default_state', 3)->nullable();
            $table->timestampTz('archived_at')->nullable();
            $table->timestampsTz();
            $table->unique(['id', 'tenant_id'], 'scenarios_id_tenant_unique');
            $table->index(['tenant_id', 'archived_at', 'created_at']);
        });

        DB::statement('ALTER TABLE scenarios ADD CONSTRAINT scenarios_horizon_range CHECK (horizon_years BETWEEN 3 AND 5)');
        DB::statement('ALTER TABLE scenarios ADD CONSTRAINT scenarios_retroactive_range CHECK (retroactive_months >= 0 AND retroactive_months <= horizon_years * 12)');
        DB::statement("ALTER TABLE scenarios ADD CONSTRAINT scenarios_name_filled CHECK (btrim(name) <> '')");
        DB::statement("ALTER TABLE scenarios ADD CONSTRAINT scenarios_group_changes_object CHECK (jsonb_typeof(group_changes) = 'object')");
        DB::statement("ALTER TABLE scenarios ADD CONSTRAINT scenarios_term_changes_list CHECK (jsonb_typeof(term_changes) = 'array')");
        DB::statement("ALTER TABLE scenarios ADD CONSTRAINT scenarios_assumptions_object CHECK (jsonb_typeof(assumptions) = 'object')");
    }

    public function down(): void
    {
        Schema::dropIfExists('scenarios');
    }
};
