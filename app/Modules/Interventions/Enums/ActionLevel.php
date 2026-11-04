<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Enums;

/**
 * The three levels of action of num. 8.5 (criterio C-32): organizational (a), group (b) and individual (c).
 */
enum ActionLevel: int
{
    case Organizational = 1;
    case Group = 2;
    case Individual = 3;

    public function label(): string
    {
        return match ($this) {
            self::Organizational => 'Primer nivel: organizacional',
            self::Group => 'Segundo nivel: grupal',
            self::Individual => 'Tercer nivel: individual',
        };
    }
}
