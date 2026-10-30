<?php

declare(strict_types=1);

namespace App\Modules\Results\Support;

use Carbon\CarbonInterface;

/**
 * Moments as people read them, in the display time zone (CLAUDE.md: stored in UTC, shown in America/Mexico_City).
 */
final class ResultsClock
{
    /** A day as the design system writes it: "3 nov 2026". */
    public static function day(CarbonInterface $moment): string
    {
        return self::format($moment, 'D MMM YYYY');
    }

    /** A day and time: "3 nov 2026, 14:30". */
    public static function moment(CarbonInterface $moment): string
    {
        return self::format($moment, 'D MMM YYYY, HH:mm');
    }

    private static function format(CarbonInterface $moment, string $format): string
    {
        // Carbon's Spanish months end in a dot ("nov."); the design system writes "3 nov 2026".
        return str_replace('.', '', $moment->setTimezone(config()->string('app.display_timezone'))->isoFormat($format));
    }
}
