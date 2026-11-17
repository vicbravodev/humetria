<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each time the console prepares or resets the demo company, with its progress. Platform data: no tenant_id. The partial
 * unique index is the lock: at most one run is running.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kind', 10);
            $table->string('status', 10);
            $table->string('step', 60)->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
            // The class of what failed, never its message: a database error's message can carry values.
            $table->string('error_class')->nullable();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE demo_runs
                ADD CONSTRAINT demo_runs_kind_known CHECK (kind IN ('prepare', 'reset')),
                ADD CONSTRAINT demo_runs_status_known CHECK (status IN ('running', 'finished', 'failed')),
                ADD CONSTRAINT demo_runs_finished_when_done CHECK ((status = 'running') = (finished_at IS NULL))
        SQL);
        DB::statement("CREATE UNIQUE INDEX demo_runs_one_running ON demo_runs ((true)) WHERE status = 'running'");
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_runs');
    }
};
