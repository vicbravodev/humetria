<?php

declare(strict_types=1);

namespace App\Modules\Telework\Enums;

/**
 * What was done about an accident notice (6.9; rediseño v2, board V3-Aviso). Never clinical detail: that stays in the
 * person's medical record, outside Humetría. The person reads these in their portal.
 */
enum NoticeAction: string
{
    case MedicalCare = 'medical_care';
    case PlaceAdjusted = 'place_adjusted';
    case TasksChanged = 'tasks_changed';
    case Leave = 'leave';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::MedicalCare => 'Se canalizó a atención médica',
            self::PlaceAdjusted => 'Se ajustó su lugar o equipo',
            self::TasksChanged => 'Se acordaron cambios en sus tareas',
            self::Leave => 'Se le dio un permiso para recuperarse',
            self::Other => 'Otro',
        };
    }
}
