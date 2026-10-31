<?php

declare(strict_types=1);

namespace App\Modules\Telework\Enums;

/** Where a workplace stands. Always derived (`WorkplaceStanding`), never stored. */
enum WorkplaceStatus: string
{
    case Pending = 'pending';
    case Valid = 'valid';
    case ValidWithPlan = 'valid_with_plan';
    case NotValid = 'not_valid';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente de validar',
            self::Valid => 'Validado',
            self::ValidWithPlan => 'Validado con plan de acción',
            self::NotValid => 'No apto',
            self::Retired => 'Retirado',
        };
    }
}
