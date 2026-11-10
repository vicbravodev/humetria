<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Enums;

/**
 * Where a company stands: a restriction a person set wins; otherwise it is computed from its dates (never stored).
 */
enum TenantStatus: string
{
    case NotStarted = 'not_started';
    case Trial = 'trial';
    case Active = 'active';
    case TrialEnded = 'trial_ended';
    case Expired = 'expired';
    case ReadOnly = 'read_only';
    case Suspended = 'suspended';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Sin iniciar',
            self::Trial => 'En prueba',
            self::Active => 'Activa',
            self::TrialEnded => 'Prueba terminada',
            self::Expired => 'Vencida',
            self::ReadOnly => 'Solo lectura',
            self::Suspended => 'Suspendida',
            self::Archived => 'Archivada',
        };
    }

    public function isBlocked(): bool
    {
        return match ($this) {
            self::TrialEnded, self::Expired, self::ReadOnly, self::Suspended, self::Archived => true,
            self::NotStarted, self::Trial, self::Active => false,
        };
    }
}
