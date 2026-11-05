<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Enums;

/** How often a benefit is paid, when it is an amount per period. */
enum Frequency: string
{
    case Daily = 'daily';
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Cada día',
            self::Monthly => 'Cada mes',
            self::Yearly => 'Cada año',
        };
    }
}
