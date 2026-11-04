<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Enums;

/**
 * Whether the link of the page of the workers opens (criterio C-55): active, switched off by RH, or past its expiry day.
 */
enum PageLinkStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activo',
            self::Disabled => 'Desactivado',
            self::Expired => 'Vencido',
        };
    }
}
