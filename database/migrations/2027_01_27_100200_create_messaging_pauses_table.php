<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The stops of the whole WhatsApp feature, for every company: the automatic pause (blocks and spam reports piling up) and the
 * platform's monthly cap. At most one is open; only an operator closes it (messaging:resume).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messaging_pauses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('reason', 20);
            $table->timestampTz('started_at');
            $table->timestampTz('ended_at')->nullable();
        });

        DB::statement("ALTER TABLE messaging_pauses ADD CONSTRAINT messaging_pauses_reason_known CHECK (reason IN ('failure_rate', 'platform_cap'))");
        DB::statement('CREATE UNIQUE INDEX messaging_pauses_one_open ON messaging_pauses ((true)) WHERE ended_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('messaging_pauses');
    }
};
