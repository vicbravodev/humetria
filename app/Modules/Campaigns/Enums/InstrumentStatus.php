<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Enums;

/**
 * Where a person stands with one guide of a campaign. Completed records only that they answered, never what.
 */
enum InstrumentStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Completed => 'Contestó',
            self::Cancelled => 'Cancelada',
        };
    }
}
