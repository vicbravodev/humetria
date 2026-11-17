<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The door of the demo (a QR the audience scans): a session the console opens for a while, with only the hash of its random
 * code, and which door person each visitor took. An assignment says nothing of what the person answered: it has no key to an
 * invitation, a token or a response, only the worker taken and when. Resetting the demo deletes both with the rest of the demo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_door_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->string('code_hash', 64)->unique();
            $table->timestampTz('expires_at');
            $table->timestampTz('closed_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('demo_door_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('session_id')->constrained('demo_door_sessions')->cascadeOnDelete();
            $table->uuid('employee_id');
            $table->timestampTz('claimed_at');

            $table->foreign(['employee_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('employees')->restrictOnDelete();
            // A worker is handed out once until the demo is reset: whoever had them may have answered already.
            // (Stricter than once per session, which it implies.)
            $table->unique(['tenant_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_door_assignments');
        Schema::dropIfExists('demo_door_sessions');
    }
};
