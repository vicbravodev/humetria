<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Enums;

/**
 * Who a benefit is for: the unionized or the trusted staff. Its values are the ones the roster uses for a person's group
 * (Organization's StaffGroup, which is internal to that module and so is not used here).
 */
enum PayGroup: string
{
    case Unionized = 'unionized';
    case Trusted = 'trusted';

    public function label(): string
    {
        return match ($this) {
            self::Unionized => 'Sindicalizados',
            self::Trusted => 'Confianza',
        };
    }
}
