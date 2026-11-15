<?php

declare(strict_types=1);

namespace App\Modules\Billing\Enums;

/** An extra charge on a company: every period (accompaniment) or once (implementation, a CCT study). */
enum ChargeKind: string
{
    case Recurring = 'recurring';
    case OneTime = 'one_time';

    public function label(): string
    {
        return match ($this) {
            self::Recurring => 'Recurrente',
            self::OneTime => 'Una sola vez',
        };
    }
}
