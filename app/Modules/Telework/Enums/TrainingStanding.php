<?php

declare(strict_types=1);

namespace App\Modules\Telework\Enums;

/** Where a person stands against the yearly training (numeral 5.9): up to date, within a month of it, or past it. */
enum TrainingStanding: string
{
    case Ok = 'ok';
    case DueSoon = 'due_soon';
    case Overdue = 'overdue';

    public function label(): string
    {
        return match ($this) {
            self::Ok => 'Al corriente',
            self::DueSoon => 'Vence pronto',
            self::Overdue => 'Vencida',
        };
    }
}
