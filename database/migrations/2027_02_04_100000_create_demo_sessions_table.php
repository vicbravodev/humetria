<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A demo session the console runs for a client: the tours chosen, and where it stands. Platform data like demo_runs: no
 * tenant_id. The partial unique index is the lock: at most one session is open (preparing, active or ending). A run may
 * now prepare a session too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('client_name', 80)->nullable();
            $table->jsonb('tours');
            // The demo's center for the session; no foreign key: it is a company's row, and each reset wipes it.
            $table->uuid('work_center_id')->nullable();
            $table->jsonb('skipped_steps')->default('[]');
            // The panel pages (route names) the presenter opened during the session, each once: the guide's «visitó» steps.
            $table->jsonb('visited')->default('[]');
            // The step the guide last showed as current: when it is done by the next read, the guide celebrates it once.
            $table->string('guide_step')->nullable();
            $table->string('status', 10);
            $table->foreignUuid('run_id')->nullable()->constrained('demo_runs')->nullOnDelete();
            $table->timestampTz('started_at');
            $table->timestampTz('ready_at')->nullable();
            $table->timestampTz('ended_at')->nullable();
        });

        DB::statement("ALTER TABLE demo_sessions ADD CONSTRAINT demo_sessions_status_known CHECK (status IN ('preparing', 'active', 'ending', 'ended', 'failed'))");
        DB::statement("CREATE UNIQUE INDEX demo_sessions_one_open ON demo_sessions ((true)) WHERE status IN ('preparing', 'active', 'ending')");
        DB::statement(<<<'SQL'
            ALTER TABLE demo_runs
                DROP CONSTRAINT demo_runs_kind_known,
                ADD CONSTRAINT demo_runs_kind_known CHECK (kind IN ('prepare', 'reset', 'session'))
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_sessions');
        DB::table('demo_runs')->where('kind', 'session')->delete();
        DB::statement(<<<'SQL'
            ALTER TABLE demo_runs
                DROP CONSTRAINT demo_runs_kind_known,
                ADD CONSTRAINT demo_runs_kind_known CHECK (kind IN ('prepare', 'reset'))
        SQL);
    }
};
