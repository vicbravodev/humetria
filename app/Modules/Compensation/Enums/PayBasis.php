<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Enums;

/**
 * How the salary of a scenario was agreed, which decides how many days a year of it costs: a daily rate pays the 365 days of
 * the year; a monthly pay is divided by 30 for the daily salary (LSS art. 29) but pays 12 months, that is 12 × 30 = 360 days.
 */
enum PayBasis: string
{
    case Daily = 'daily';
    case Monthly = 'monthly';

    /** The days of salary a year costs for one person. */
    public function paidDays(): int
    {
        return match ($this) {
            self::Daily => 365,
            self::Monthly => 360,
        };
    }
}
