<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * NOM-035 Programa de intervención (num. 8.3 and 8.4; criterio C-30): one Programa per closed evaluation, and its rows, one
 * per final score, category or domain it attends. A row keeps the highest level that led to it, never a count of
 * questionnaires: the figures are read live from Results, under the minimum in force. Nothing here points at an employee,
 * an invitation or a token (criterios C-22 and C-33).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intervention_programs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('campaign_id');
            $table->string('highest_level', 9)->nullable();
            $table->string('responsible_name', 120)->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->restrictOnDelete();
            // Null: the system drafted it when the evaluation closed.
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->foreign(['campaign_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('campaigns')->restrictOnDelete();
            $table->unique('campaign_id');
            $table->unique(['id', 'tenant_id'], 'intervention_programs_id_tenant_unique');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE intervention_programs
                ADD CONSTRAINT intervention_programs_level_known CHECK (highest_level IS NULL OR highest_level IN ('medium', 'high', 'very_high')),
                ADD CONSTRAINT intervention_programs_user_has_name CHECK (responsible_user_id IS NULL OR length(btrim(coalesce(responsible_name, ''))) > 0)
        SQL);

        Schema::create('intervention_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('program_id');
            $table->string('scope', 8);
            $table->string('scope_key', 80);
            // Frozen: the instrument is versioned and the record must read the same years from now.
            $table->string('label', 160);
            $table->string('category_key', 80)->nullable();
            $table->string('origin', 7);
            $table->string('highest_level', 9)->nullable();
            // The "análisis de cada categoría y dominio" of II.4 and III.4.
            $table->text('analysis')->nullable();
            $table->date('follow_up_due_on')->nullable();
            $table->date('follow_up_done_on')->nullable();
            $table->text('follow_up_result')->nullable();
            $table->uuid('follow_up_campaign_id')->nullable();
            $table->timestampsTz();

            $table->foreign(['program_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('intervention_programs')->restrictOnDelete();
            $table->foreign(['follow_up_campaign_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('campaigns')->restrictOnDelete();
            $table->unique(['program_id', 'scope', 'scope_key']);
            $table->unique(['id', 'tenant_id'], 'intervention_items_id_tenant_unique');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE intervention_items
                ADD CONSTRAINT intervention_items_scope_known CHECK (scope IN ('final', 'category', 'domain')),
                ADD CONSTRAINT intervention_items_origin_known CHECK (origin IN ('results', 'manual')),
                ADD CONSTRAINT intervention_items_level_known CHECK (highest_level IS NULL OR highest_level IN ('medium', 'high', 'very_high')),
                ADD CONSTRAINT intervention_items_results_have_level CHECK ((origin = 'results') = (highest_level IS NOT NULL)),
                ADD CONSTRAINT intervention_items_follow_up_has_result CHECK (follow_up_done_on IS NULL OR length(btrim(coalesce(follow_up_result, ''))) > 0)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('intervention_items');
        Schema::dropIfExists('intervention_programs');
    }
};
