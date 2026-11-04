<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The days of the Programa as people read them (CLAUDE.md: the display time zone, America/Mexico_City). The dates of an
 * action are calendar days, stored as `date`: they are written as they are, never shifted by the time zone.
 */
final class ProgramClock
{
    /** Today in the display time zone, as Y-m-d. */
    public static function today(): string
    {
        return CarbonImmutable::now(config()->string('app.display_timezone'))->toDateString();
    }

    /** A calendar day as the design system writes it: "3 nov 2026". */
    public static function day(CarbonInterface $date): string
    {
        // Carbon's Spanish months end in a dot ("nov."); the design system writes "3 nov 2026".
        return str_replace('.', '', CarbonImmutable::parse($date->format('Y-m-d'))->isoFormat('D MMM YYYY'));
    }
}
