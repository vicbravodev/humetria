<?php

declare(strict_types=1);

namespace App\Modules\Seating\Enums;

/**
 * How a seat is: in use, to be repaired, or out of service.
 */
enum SeatCondition: string
{
    case Good = 'good';
    case NeedsRepair = 'needs_repair';
    case OutOfService = 'out_of_service';

    public function label(): string
    {
        return match ($this) {
            self::Good => 'Buen estado',
            self::NeedsRepair => 'Necesita reparación',
            self::OutOfService => 'Fuera de servicio',
        };
    }
}
