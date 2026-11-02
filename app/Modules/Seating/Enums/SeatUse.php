<?php

declare(strict_types=1);

namespace App\Modules\Seating\Enums;

/**
 * Where a seat is for: the workstation itself, or an area designated for the periodic rests (criterio C-01).
 */
enum SeatUse: string
{
    case Workstation = 'workstation';
    case RestArea = 'rest_area';

    public function label(): string
    {
        return match ($this) {
            self::Workstation => 'En el puesto de trabajo',
            self::RestArea => 'Área de descanso',
        };
    }
}
