<?php

declare(strict_types=1);

namespace App\Modules\Telework\Enums;

/** What the Comisión decides about a workplace (criterio C-12). */
enum ReviewDecision: string
{
    case Valid = 'valid';
    case ValidWithPlan = 'valid_with_plan';
    case NotValid = 'not_valid';

    public function label(): string
    {
        return match ($this) {
            self::Valid => 'Validado',
            self::ValidWithPlan => 'Validado con plan de acción',
            self::NotValid => 'No validado',
        };
    }

    /** A decision that lets the person telework from the place, until it expires. */
    public function validates(): bool
    {
        return $this !== self::NotValid;
    }
}
