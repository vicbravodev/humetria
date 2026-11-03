<?php

declare(strict_types=1);

namespace App\Modules\Seating\Enums;

/**
 * Where a preventive measure stands: planned, in progress, done or cancelled. A measure is cancelled, never deleted (criterio C-17).
 */
enum MeasureStatus: string
{
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Planeada',
            self::InProgress => 'En curso',
            self::Done => 'Hecha',
            self::Cancelled => 'Cancelada',
        };
    }
}
