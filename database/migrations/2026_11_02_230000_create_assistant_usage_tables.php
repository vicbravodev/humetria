<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What each company may do with Humi and what it has used. The settings are the superadmin's: whether Humi is on and the
 * company's monthly budget of tokens (none: the platform's default). The usage keeps one row per message with its
 * counts, never its text; the row stays when the person who sent it is removed (the budget still counts it), detached.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_ai_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->unique()->constrained()->restrictOnDelete();
            $table->boolean('enabled')->default(true);
            $table->unsignedBigInteger('monthly_token_budget')->nullable();
            $table->timestampsTz();
        });

        Schema::create('ai_usage', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('participant_id')->nullable()->constrained('conversation_participants')->nullOnDelete();
            $table->string('conversation_id', 36)->nullable();
            $table->string('agent', 60);
            $table->unsignedInteger('input_tokens');
            $table->unsignedInteger('output_tokens');
            $table->json('tools');
            $table->unsignedInteger('latency_ms');
            $table->boolean('stopped')->default(false);
            $table->timestampTz('created_at');

            $table->index(['tenant_id', 'created_at']);
        });

        DB::statement('ALTER TABLE tenant_ai_settings ADD CONSTRAINT tenant_ai_settings_budget_not_negative CHECK (monthly_token_budget IS NULL OR monthly_token_budget >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage');
        Schema::dropIfExists('tenant_ai_settings');
    }
};
