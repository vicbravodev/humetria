<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Enums;

/**
 * Where an action of the Programa stands: planned, in progress, done or cancelled. An action is cancelled with a reason,
 * never deleted (criterio C-35).
 */
enum ActionStatus: string
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
