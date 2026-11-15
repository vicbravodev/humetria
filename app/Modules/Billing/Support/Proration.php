<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * The share of a period that is left when a subscription starts in the middle of it: days over days, never a fraction.
 */
final readonly class Proration
{
    public function __construct(public int $daysRemaining, public int $periodDays)
    {
        if ($daysRemaining < 1 || $daysRemaining > $periodDays) {
            throw new InvalidArgumentException("No se puede prorratear {$daysRemaining} de {$periodDays} días.");
        }
    }

    /**
     * What is left of the calendar month from the day a subscription starts, counting that day; null when it starts on
     * the 1st, which is a whole month.
     */
    public static function startingOn(CarbonImmutable $day): ?self
    {
        return $day->day === 1 ? null : new self($day->daysInMonth - $day->day + 1, $day->daysInMonth);
    }
}
