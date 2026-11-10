<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support;

use Carbon\CarbonImmutable;

/** Which day it is for the people who answer: the display time zone's, not UTC's. */
final class SurveyClock
{
    /** Today, as a date at midnight in the application's zone (how dates are stored), by the display time zone's calendar. */
    public static function day(): CarbonImmutable
    {
        return new CarbonImmutable(CarbonImmutable::now(self::zone())->toDateString());
    }

    /** Whether the last day `$closesOn` (a date) is over: a survey takes answers until that day ends in the display time zone. */
    public static function hasPassed(CarbonImmutable $closesOn): bool
    {
        return ! CarbonImmutable::now()->lessThan(CarbonImmutable::parse($closesOn->toDateString(), self::zone())->addDay());
    }

    private static function zone(): string
    {
        return config()->string('app.display_timezone');
    }
}
