<?php

declare(strict_types=1);

namespace App\Modules\Results\Enums;

/**
 * Where the channeling of a person who requires a clinical assessment stands once HR has recorded it. A person with no
 * record is still pending.
 */
enum ReferralStatus: string
{
    case Referred = 'referred';
    case Attended = 'attended';

    public function label(): string
    {
        return match ($this) {
            self::Referred => 'Canalizada',
            self::Attended => 'Atendida',
        };
    }
}
