<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The days of the disclosure as people read them (CLAUDE.md: stored in UTC, shown in the display time zone).
 */
final class Days
{
    /** A moment as the design system writes a day, in the display time zone: "3 nov 2026". */
    public static function moment(CarbonInterface $moment): string
    {
        // Carbon's Spanish months end in a dot ("nov."); the design system writes "3 nov 2026".
        return str_replace('.', '', $moment->setTimezone(config()->string('app.display_timezone'))->isoFormat('D MMM YYYY'));
    }

    /** A calendar day (a date stored as a day, with no time zone) as the design system writes it: "3 nov 2026". */
    public static function calendar(CarbonInterface $day): string
    {
        return str_replace('.', '', CarbonImmutable::parse($day->format('Y-m-d'))->isoFormat('D MMM YYYY'));
    }

    /** Today in the display time zone, as Y-m-d. */
    public static function today(): string
    {
        return CarbonImmutable::now(config()->string('app.display_timezone'))->toDateString();
    }
}
