<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support\Pulse;

use App\Modules\Engagement\Enums\PulseFrequency;
use App\Modules\Engagement\Enums\PulseMode;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/** When a company's pulse goes out and what it looks at each time. Pure. */
final class PulsePlanner
{
    /** The last day of the month a pulse can be set on: every month has it. */
    public const int LAST_DAY = 28;

    private const array MONTHLY = [PulseMode::FollowUp, PulseMode::Tour, PulseMode::Manager];

    /** The manager every other cycle of four: with a quarterly pulse a cycle of three would make every pulse about the manager. */
    private const array QUARTERLY = [PulseMode::FollowUp, PulseMode::Tour, PulseMode::Manager, PulseMode::Tour];

    /**
     * @param  int  $position  the pulses the company already launched
     */
    public static function modeFor(PulseFrequency $frequency, int $position, bool $hasPriorities): PulseMode
    {
        $cycle = $frequency === PulseFrequency::Monthly ? self::MONTHLY : self::QUARTERLY;
        $mode = $cycle[$position % count($cycle)];

        return $mode === PulseMode::FollowUp && ! $hasPriorities ? PulseMode::Tour : $mode;
    }

    /**
     * What RH may choose by hand.
     *
     * @return list<PulseMode>
     */
    public static function available(bool $hasPriorities): array
    {
        return array_values(array_filter(PulseMode::cases(), fn (PulseMode $mode): bool => $hasPriorities || $mode !== PulseMode::FollowUp));
    }

    /**
     * The first day after `$after` that is `$dayOfMonth` of a month the frequency runs in: any month for a monthly pulse,
     * the last of each calendar quarter (March, June, September, December) for a quarterly one.
     *
     * @throws InvalidArgumentException when the day is not between 1 and 28
     */
    public static function nextRun(PulseFrequency $frequency, int $dayOfMonth, CarbonImmutable $after): CarbonImmutable
    {
        if ($dayOfMonth < 1 || $dayOfMonth > self::LAST_DAY) {
            throw new InvalidArgumentException("The day [{$dayOfMonth}] does not exist in every month.");
        }

        $candidate = $after->startOfMonth()->day($dayOfMonth);

        while ($candidate->lessThanOrEqualTo($after->startOfDay()) || $candidate->month % $frequency->months() !== 0) {
            $candidate = $candidate->addMonthNoOverflow()->day($dayOfMonth);
        }

        return $candidate;
    }
}
