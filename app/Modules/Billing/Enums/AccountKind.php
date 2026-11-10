<?php

declare(strict_types=1);

namespace App\Modules\Billing\Enums;

/** Who pays: a company that pays for itself or for its group (direct), or a consultant who pays for the companies of their clients. */
enum AccountKind: string
{
    case Direct = 'direct';
    case Consultant = 'consultant';

    public function label(): string
    {
        return match ($this) {
            self::Direct => 'Directa',
            self::Consultant => 'Consultor',
        };
    }
}
