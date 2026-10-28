<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use Carbon\CarbonInterface;

final class DisplayDate
{
    /**
     * A moment as the design system writes a day, in the display time zone: "27 oct 2026".
     */
    public static function day(CarbonInterface $moment): string
    {
        // Carbon's Spanish months end in a dot ("oct."); the design system writes "27 oct 2026".
        return str_replace('.', '', $moment->setTimezone(config()->string('app.display_timezone'))->isoFormat('D MMM YYYY'));
    }
}
