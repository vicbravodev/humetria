<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Enums;

/**
 * Who answers Guía III: everyone in the work center, or the representative sample of III.1 (criterio C-09).
 * Guía II always goes to everyone (II.1).
 */
enum Population: string
{
    case All = 'all';
    case Sample = 'sample';

    public function label(): string
    {
        return match ($this) {
            self::All => 'Todos',
            self::Sample => 'Muestra',
        };
    }
}
