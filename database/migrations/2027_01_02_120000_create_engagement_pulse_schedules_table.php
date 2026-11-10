<?php

declare(strict_types=1);

use App\Modules\Engagement\Enums\PulseFrequency;
use App\Modules\Engagement\Enums\PulseMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When a company's pulse goes out by itself: monthly or quarterly, on a day of the month (1 to 28, which every month has),
 * open for a few days. One row per company; pausing keeps it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engagement_pulse_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->unique()->constrained()->restrictOnDelete();
            $table->string('frequency', 12);
            $table->unsignedSmallInteger('day_of_month');
            $table->unsignedSmallInteger('open_days')->default(7);
            $table->boolean('active')->default(true);
            $table->date('next_run_on');
            $table->string('next_mode', 20)->nullable();
            $table->date('announced_for')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->index(['active', 'next_run_on']);
        });

        $frequencies = implode(', ', array_map(fn (PulseFrequency $case): string => "'{$case->value}'", PulseFrequency::cases()));
        $modes = implode(', ', array_map(fn (PulseMode $case): string => "'{$case->value}'", PulseMode::cases()));

        DB::statement(<<<SQL
            ALTER TABLE engagement_pulse_schedules
                ADD CONSTRAINT engagement_pulse_schedules_frequency_known CHECK (frequency IN ({$frequencies})),
                ADD CONSTRAINT engagement_pulse_schedules_mode_known CHECK (next_mode IS NULL OR next_mode IN ({$modes})),
                ADD CONSTRAINT engagement_pulse_schedules_day_valid CHECK (day_of_month BETWEEN 1 AND 28),
                ADD CONSTRAINT engagement_pulse_schedules_open_days_valid CHECK (open_days BETWEEN 3 AND 14)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('engagement_pulse_schedules');
    }
};
