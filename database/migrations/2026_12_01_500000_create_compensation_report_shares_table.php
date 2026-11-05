<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Temporary links to the report of a scenario, for people with no account. Only the hash of the link's token is kept, and a
 * link lasts 7 days at most, which the database enforces too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compensation_report_shares', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('scenario_id');
            $table->char('token_hash', 64)->unique();
            $table->timestampTz('expires_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->foreign(['scenario_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('scenarios')->restrictOnDelete();
            $table->index(['tenant_id', 'scenario_id', 'expires_at']);
        });

        DB::statement("ALTER TABLE compensation_report_shares ADD CONSTRAINT compensation_report_shares_at_most_7_days CHECK (expires_at > created_at AND expires_at <= created_at + interval '7 days')");
    }

    public function down(): void
    {
        Schema::dropIfExists('compensation_report_shares');
    }
};
