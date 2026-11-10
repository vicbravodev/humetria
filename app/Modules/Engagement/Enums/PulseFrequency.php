<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Enums;

/** How often a company's pulse goes out. Nothing less often and no "always open": real anonymity cannot stop duplicates. */
enum PulseFrequency: string
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';

    public function months(): int
    {
        return match ($this) {
            self::Monthly => 1,
            self::Quarterly => 3,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Mensual',
            self::Quarterly => 'Trimestral',
        };
    }
}
