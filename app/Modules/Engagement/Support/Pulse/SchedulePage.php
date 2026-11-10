<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support\Pulse;

use App\Modules\Engagement\Enums\PulseFrequency;
use App\Modules\Engagement\Models\PulseSchedule;

/**
 * The card of the schedule in the home of the pulse: how often it goes out and what the next one will be. Dates and
 * counts only.
 */
final class SchedulePage
{
    /**
     * @return array{frequency: string, frequencyLabel: string, dayOfMonth: int, openDays: int, active: bool, nextRunOn: string, nextMode: string|null, upcoming: array{mode: string, modeLabel: string, items: int}}|null
     */
    public static function props(): ?array
    {
        $schedule = PulseSchedule::query()->first();

        if ($schedule === null) {
            return null;
        }

        $upcoming = UpcomingPulse::resolve($schedule->frequency, $schedule->next_mode);

        return [
            'frequency' => $schedule->frequency->value,
            'frequencyLabel' => $schedule->frequency->label(),
            'dayOfMonth' => $schedule->day_of_month,
            'openDays' => $schedule->open_days,
            'active' => $schedule->active,
            'nextRunOn' => $schedule->next_run_on->toDateString(),
            'nextMode' => $schedule->next_mode?->value,
            'upcoming' => ['mode' => $upcoming->mode->value, 'modeLabel' => $upcoming->mode->label(), 'items' => count($upcoming->refs)],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function frequencies(): array
    {
        return array_map(fn (PulseFrequency $frequency): array => ['value' => $frequency->value, 'label' => $frequency->label()], PulseFrequency::cases());
    }
}
