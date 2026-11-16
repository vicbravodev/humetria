<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The personal portal link goes: the person's pages read their session (WorkerAccess), and no address of theirs carries a
 * credential that serves more than once. No company uses the platform yet, so no row is kept. Going back makes the table
 * again as it stood (a derived link with its version), empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('portal_links');
    }

    public function down(): void
    {
        Schema::create('portal_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('employee_id');
            $table->char('token_hash', 64)->unique();
            $table->timestampsTz();
            $table->unsignedInteger('version')->default(1);

            $table->foreign(['employee_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('employees')->restrictOnDelete();
            $table->unique('employee_id');
        });

        DB::statement('ALTER TABLE portal_links ADD CONSTRAINT portal_links_version_positive CHECK (version >= 1)');
    }
};
