<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Enums;

/**
 * Where a Programa stands: no actions yet, running, running with overdue actions, or done. It shows what is recorded and
 * what is missing; it never certifies compliance.
 */
enum ProgramState: string
{
    case NoActions = 'none';
    case InProgress = 'in_progress';
    case Overdue = 'overdue';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::NoActions => 'Sin acciones',
            self::InProgress => 'En curso',
            self::Overdue => 'Con acciones vencidas',
            self::Done => 'Terminado',
        };
    }
}
