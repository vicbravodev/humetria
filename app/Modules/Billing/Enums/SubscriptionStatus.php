<?php

declare(strict_types=1);

namespace App\Modules\Billing\Enums;

/** Where the commercial relationship stands. It is not access: that is Tenancy's, and setting this changes none of it. */
enum SubscriptionStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case PastDue = 'past_due';
    case CancelScheduled = 'cancel_scheduled';
    case Ended = 'ended';

    public function label(): string
    {
        return match ($this) {
            self::Trial => 'En prueba',
            self::Active => 'Activa',
            self::PastDue => 'Con saldo vencido',
            self::CancelScheduled => 'Cancelación programada',
            self::Ended => 'Terminada',
        };
    }

    /** Whether the company pays for the plan now: what counts toward a consultant's Integral discount. */
    public function isPaying(): bool
    {
        return match ($this) {
            self::Active, self::CancelScheduled => true,
            self::Trial, self::PastDue, self::Ended => false,
        };
    }
}
