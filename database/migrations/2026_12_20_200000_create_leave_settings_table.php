<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vacaciones: one row per company that uses the tool. The day it started (criterio C-21: periods are granted from it on,
 * earlier ones come in as opening balances) and the working week of the «Ley» policy (criterio C-04).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->unique()->constrained()->restrictOnDelete();
            $table->date('tracking_since');
            $table->string('statutory_work_week', 7)->default('mon_fri');
            $table->timestampsTz();
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE leave_settings
                ADD CONSTRAINT leave_settings_statutory_work_week_known CHECK (statutory_work_week IN ('mon_fri', 'mon_sat'));
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_settings');
    }
};
