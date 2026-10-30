<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Enums;

/**
 * Where a person stands with the guides of a campaign (or with one of them), from what is still to do: a guide
 * pending makes them pending, or without email, or failed, by how their link went; else a cancelled guide makes
 * them cancelled; else they answered.
 */
enum Standing: string
{
    case Pending = 'pending';
    case Unreachable = 'unreachable';
    case Failed = 'failed';
    case Answered = 'answered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendientes',
            self::Unreachable => 'Sin correo',
            self::Failed => 'Envío fallido',
            self::Answered => 'Contestaron',
            self::Cancelled => 'Canceladas',
        };
    }
}
