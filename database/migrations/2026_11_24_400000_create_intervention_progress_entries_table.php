<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The control of the progress of a Programa (num. 8.4 d): one row per step an action takes, with the day, the state it moved
 * to, a note and optionally a file of evidence. Insert-only (criterio C-35): it has no updated_at and the code never edits
 * or deletes a row. It records the day, not the hour.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intervention_progress_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('action_id');
            $table->date('recorded_on');
            $table->string('status', 11);
            $table->text('note')->nullable();
            $table->string('evidence_path')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at');

            $table->foreign(['action_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('intervention_actions')->restrictOnDelete();
            $table->index(['action_id', 'recorded_on']);
        });

        DB::statement("ALTER TABLE intervention_progress_entries ADD CONSTRAINT intervention_progress_entries_status_known CHECK (status IN ('planned', 'in_progress', 'done', 'cancelled'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('intervention_progress_entries');
    }
};
