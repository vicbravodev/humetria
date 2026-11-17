<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which scheduled task already ran for which work center or company on which of its local days (design of time zones §4,
 * phase 3): the unique row makes a dispatcher that ticks every 15 minutes run each one exactly once per local day.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('local_runs', function (Blueprint $table) {
            $table->id();
            $table->string('task', 60);
            $table->uuid('scope_id');
            $table->date('local_date');
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['task', 'scope_id', 'local_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('local_runs');
    }
};
