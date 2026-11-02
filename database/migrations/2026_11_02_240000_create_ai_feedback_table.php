<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The thumb up or down a person gives to an answer of Humi, with a reason from a closed list when it is down. No free
 * text: what a person would write about an answer can name someone. One rating per person and answer; the row stays,
 * detached, when the person is removed from the company.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_feedback', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('participant_id')->nullable()->constrained('conversation_participants')->nullOnDelete();
            $table->string('conversation_id', 36);
            $table->string('message_id', 36);
            $table->string('rating', 4);
            $table->string('reason', 20)->nullable();
            $table->timestampTz('created_at');

            $table->unique(['participant_id', 'message_id']);
            $table->index(['tenant_id', 'created_at']);
        });

        DB::statement("ALTER TABLE ai_feedback ADD CONSTRAINT ai_feedback_rating_known CHECK (rating IN ('up', 'down'))");
        DB::statement("ALTER TABLE ai_feedback ADD CONSTRAINT ai_feedback_reason_known CHECK (reason IS NULL OR reason IN ('incorrect', 'incomplete', 'unclear', 'other'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_feedback');
    }
};
