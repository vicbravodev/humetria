<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** When the demo of a request is scheduled, and why a request was discarded. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demo_requests', function (Blueprint $table): void {
            $table->timestampTz('scheduled_at')->nullable();
            $table->string('discard_reason', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('demo_requests', function (Blueprint $table): void {
            $table->dropColumn(['scheduled_at', 'discard_reason']);
        });
    }
};
