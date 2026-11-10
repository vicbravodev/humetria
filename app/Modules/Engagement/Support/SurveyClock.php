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
        return new CarbonImmutable(CarbonImmutable::now(config()->string('app.display_timezone'))->toDateString());
    }
}
