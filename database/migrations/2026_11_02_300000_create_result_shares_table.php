<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Temporary links to an evaluation's report, for people with no account. Only the hash of the link's token is kept, and
 * a link lasts 48 hours at most, which the database enforces too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('result_shares', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('campaign_id');
            $table->char('token_hash', 64)->unique();
            $table->timestampTz('expires_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->foreign(['campaign_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('campaigns')->cascadeOnDelete();
            $table->index(['tenant_id', 'campaign_id', 'expires_at']);
        });

        DB::statement("ALTER TABLE result_shares ADD CONSTRAINT result_shares_at_most_48_hours CHECK (expires_at > created_at AND expires_at <= created_at + interval '48 hours')");
    }

    public function down(): void
    {
        Schema::dropIfExists('result_shares');
    }
};
