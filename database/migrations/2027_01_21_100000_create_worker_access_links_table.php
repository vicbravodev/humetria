<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WorkerAccess: the one-time links a person enters with. Only the hash of the token is kept; the token is random. `issued` (by
 * RH, who is `issued_by`) or `requested` (by the person, no `issued_by`). A conditional UPDATE spends one once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worker_access_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('employee_id');
            $table->char('token_hash', 64)->unique();
            $table->string('kind', 20);
            $table->foreignId('issued_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('expires_at');
            $table->timestampTz('used_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'employee_id']);
            $table->foreign(['employee_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('employees')->restrictOnDelete();
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE worker_access_links
                ADD CONSTRAINT worker_access_links_kind_valid CHECK (kind IN ('issued', 'requested')),
                ADD CONSTRAINT worker_access_links_issuer_matches_kind CHECK ((kind = 'issued') = (issued_by IS NOT NULL));
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_access_links');
    }
};
