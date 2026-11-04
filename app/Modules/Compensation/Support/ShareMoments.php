<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support;

use Carbon\CarbonInterface;

/** A moment as people read it, in the display time zone (stored in UTC, shown in America/Mexico_City): "3 nov 2026, 14:30". */
final class ShareMoments
{
    public static function text(CarbonInterface $moment): string
    {
        // Carbon's Spanish months end in a dot ("nov."); the design system writes "3 nov 2026".
        return str_replace('.', '', $moment->setTimezone(config()->string('app.display_timezone'))->isoFormat('D MMM YYYY, HH:mm'));
    }
}
