<?php

declare(strict_types=1);

use App\Modules\Engagement\Enums\PulseMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** What a pulse looks at besides its fixed questions: set on every pulse and on nothing else. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('engagement_surveys', function (Blueprint $table) {
            $table->string('pulse_mode', 20)->nullable();
        });

        // A pulse drafted before the modes existed was the full questionnaire; a tour is the closest thing.
        DB::table('engagement_surveys')->where('kind', 'pulse')->update(['pulse_mode' => PulseMode::Tour->value]);

        $modes = implode(', ', array_map(fn (PulseMode $mode): string => "'{$mode->value}'", PulseMode::cases()));

        DB::statement(<<<SQL
            ALTER TABLE engagement_surveys
                ADD CONSTRAINT engagement_surveys_pulse_mode_known CHECK (pulse_mode IS NULL OR pulse_mode IN ({$modes})),
                ADD CONSTRAINT engagement_surveys_pulse_mode_of_pulse CHECK ((kind = 'pulse') = (pulse_mode IS NOT NULL))
        SQL);
    }

    public function down(): void
    {
        Schema::table('engagement_surveys', function (Blueprint $table) {
            $table->dropColumn('pulse_mode');
        });
    }
};
