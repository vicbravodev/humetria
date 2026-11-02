<?php

declare(strict_types=1);

namespace App\Modules\Seating\Enums;

/**
 * The sector a work center declares (criterio C-03): the law reaches services, commerce and the like, and industrial
 * centers only where the nature of the work allows seating.
 */
enum Sector: string
{
    case Services = 'services';
    case Commerce = 'commerce';
    case Analogous = 'analogous';
    case Industrial = 'industrial';

    public function label(): string
    {
        return match ($this) {
            self::Services => 'Servicios',
            self::Commerce => 'Comercio',
            self::Analogous => 'Análogo',
            self::Industrial => 'Industrial',
        };
    }
}
