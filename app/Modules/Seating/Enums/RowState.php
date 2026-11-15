<?php

declare(strict_types=1);

namespace App\Modules\Seating\Enums;

/**
 * Where a requirement of a work center stands on the board. None of them is an «apto»: they are counts of what was
 * registered. A warning is a requirement that is met, with something to review. Pending is a requirement with nothing registered yet to
 * look at: it is neither met nor missing, and never counts as met. Not applicable is a requirement no position of the center can trigger (a rest seat where every seat is at the workstation): it
 * is said so and left out of the count.
 */
enum RowState: string
{
    case Met = 'met';
    case Warning = 'warning';
    case Missing = 'missing';
    case Pending = 'pending';
    case NotApplicable = 'not_applicable';

    public function label(): string
    {
        return match ($this) {
            self::Met => 'Cumple',
            self::Warning => 'Con aviso',
            self::Missing => 'Falta',
            self::Pending => 'Sin registros',
            self::NotApplicable => 'No aplica',
        };
    }
}
