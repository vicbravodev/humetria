<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Enums;

/**
 * Where an invitation stands, computed from its dates (never stored).
 */
enum InvitationStatus: string
{
    case Pending = 'pending';
    case Expired = 'expired';
    case Accepted = 'accepted';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Invitación enviada',
            self::Expired => 'Vencida',
            self::Accepted => 'Aceptada',
            self::Cancelled => 'Cancelada',
        };
    }
}
