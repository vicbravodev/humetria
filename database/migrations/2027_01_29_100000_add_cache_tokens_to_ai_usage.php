<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The tokens of a message to Humi that the provider read from or wrote to its prompt cache, so the saving shows next to
 * the cost. Null when the provider reports none and for the rows written before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_usage', function (Blueprint $table): void {
            $table->unsignedInteger('cache_read_tokens')->nullable();
            $table->unsignedInteger('cache_write_tokens')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ai_usage', function (Blueprint $table): void {
            $table->dropColumn(['cache_read_tokens', 'cache_write_tokens']);
        });
    }
};
