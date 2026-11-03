<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Enums;

/**
 * Where a row of the Programa stands, from its actions that are not cancelled: none yet, running, or all done.
 */
enum RowState: string
{
    case NoActions = 'none';
    case InProgress = 'in_progress';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::NoActions => 'Sin acciones',
            self::InProgress => 'En curso',
            self::Done => 'Terminada',
        };
    }
}
