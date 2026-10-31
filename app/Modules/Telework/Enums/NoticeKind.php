<?php

declare(strict_types=1);

namespace App\Modules\Telework\Enums;

/** The three written notices a teleworker sends (numerals 6.3, 6.7 and 6.9; criterio C-26). */
enum NoticeKind: string
{
    case ConditionChange = 'condition_change';
    case AddressChange = 'address_change';
    case WorkRisk = 'work_risk';

    public function label(): string
    {
        return match ($this) {
            self::ConditionChange => 'Alteración de condiciones, fuerza mayor o acontecimiento traumático severo',
            self::AddressChange => 'Cambio de domicilio',
            self::WorkRisk => 'Riesgo de trabajo',
        };
    }
}
