<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Enums;

/**
 * Where a row of the Programa stands, from its actions that are not cancelled: none yet, only planned ones (not started),
 * running, all done while its follow-up evaluation (num. 8.4 e) waits, or all done.
 */
enum RowState: string
{
    case NoActions = 'none';
    case NotStarted = 'planned';
    case InProgress = 'in_progress';
    case AwaitingFollowUp = 'follow_up';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::NoActions => 'Sin acciones',
            self::NotStarted => 'Sin empezar',
            self::InProgress => 'En curso',
            self::AwaitingFollowUp => 'Hecha, falta la evaluación posterior',
            self::Done => 'Terminada',
        };
    }
}
