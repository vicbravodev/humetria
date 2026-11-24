<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Notifications: the push subscription of a person's phone (what the browser gave when they allowed the notices). The row is
 * disposable: it goes with the person, with a 404 or 410 of the push service, or when their access is revoked. The keys are kept
 * though a notice with no body does not read them: a notice with a body later would not ask anyone to subscribe again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('employee_id');
            $table->text('endpoint');
            $table->string('p256dh', 100);
            $table->string('auth', 32);
            $table->timestampTz('last_success_at', 6)->nullable();
            $table->timestampsTz();

            $table->foreign(['employee_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('employees')->cascadeOnDelete();
            $table->unique(['tenant_id', 'endpoint']);
            $table->index(['tenant_id', 'employee_id']);
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE push_subscriptions
                ADD CONSTRAINT push_subscriptions_endpoint_https CHECK (endpoint LIKE 'https://%'),
                ADD CONSTRAINT push_subscriptions_endpoint_size CHECK (char_length(endpoint) <= 2048);
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
