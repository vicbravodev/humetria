<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which provider and model answered a message to Humi, so a change of model shows in its cost and speed. Null for the
 * rows written before, and for the calls of other modules that only count their tokens (AiAccess::record).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_usage', function (Blueprint $table): void {
            $table->string('provider', 50)->nullable();
            $table->string('model', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ai_usage', function (Blueprint $table): void {
            $table->dropColumn(['provider', 'model']);
        });
    }
};
