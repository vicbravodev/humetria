<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Support;

use Carbon\CarbonImmutable;

/**
 * The days of the channel are days in the display time zone: a complaint keeps only the day it came, never the hour.
 */
final class Dates
{
    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(config()->string('app.display_timezone'))->startOfDay();
    }
}
