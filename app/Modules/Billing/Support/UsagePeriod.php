<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use Carbon\CarbonImmutable;

/** The month a use belongs to: [first of the month 00:00, first of the next), in the display time zone (Mexico City). */
final class UsagePeriod
{
    public static function containing(CarbonImmutable $moment): CarbonImmutable
    {
        return $moment->setTimezone(config()->string('app.display_timezone'))->startOfMonth()->startOfDay();
    }

    public static function now(): CarbonImmutable
    {
        return self::containing(CarbonImmutable::now());
    }

    /** "Noviembre 2026". */
    public static function label(CarbonImmutable $period): string
    {
        return ucfirst($period->isoFormat('MMMM YYYY'));
    }
}
