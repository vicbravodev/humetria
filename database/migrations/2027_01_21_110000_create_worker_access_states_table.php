<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WorkerAccess: one row per person, made the first time it is needed. Every session that entered before `valid_after` is over.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worker_access_states', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('employee_id')->unique();
            $table->timestampTz('valid_after', 6);
            $table->timestampsTz();

            $table->foreign(['employee_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('employees')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_access_states');
    }
};
