<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support\Pulse;

use App\Modules\Engagement\Enums\PulseMode;
use App\Modules\Engagement\Enums\SurveyKind;
use App\Modules\Engagement\Enums\SurveyStatus;
use App\Modules\Engagement\Models\Survey;

/** What the company has already done, which decides what its next pulse asks. */
final class PulseHistory
{
    /** The pulses already launched (the calendar of modes goes by them). */
    public static function launched(): int
    {
        return Survey::query()->where('kind', SurveyKind::Pulse)->whereNotNull('launched_at')->count();
    }

    /** The tours already drafted: the next one covers the other half of the drivers. */
    public static function tours(): int
    {
        return Survey::query()->where('kind', SurveyKind::Pulse)->where('pulse_mode', PulseMode::Tour)->count();
    }

    /** Whether the company closed a survey before, so the loop question has something to ask about. */
    public static function hasPreviousWave(): bool
    {
        return Survey::query()->where('status', SurveyStatus::Closed)->exists();
    }
}
