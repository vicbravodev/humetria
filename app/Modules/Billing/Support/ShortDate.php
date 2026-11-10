<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use Carbon\CarbonInterface;

/** A day as the design system writes it: "27 oct 2026". */
final class ShortDate
{
    public static function format(CarbonInterface $day): string
    {
        // Carbon's Spanish months end in a dot ("oct."); the design system writes none.
        return str_replace('.', '', $day->isoFormat('D MMM YYYY'));
    }

    /** A month as the design system writes it: "dic 2026". */
    public static function month(CarbonInterface $day): string
    {
        return str_replace('.', '', $day->isoFormat('MMM YYYY'));
    }
}
