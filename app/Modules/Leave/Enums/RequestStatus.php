<?php

declare(strict_types=1);

namespace App\Modules\Leave\Enums;

/**
 * Where a request stands. An approved one becomes taken the day after its last day.
 */
enum RequestStatus: string
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Taken = 'taken';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Solicitada',
            self::Approved => 'Aprobada',
            self::Rejected => 'Rechazada',
            self::Cancelled => 'Cancelada',
            self::Taken => 'Disfrutada',
        };
    }

    /**
     * The ones that hold their dates: the person cannot ask for the same days again.
     *
     * @return list<self>
     */
    public static function live(): array
    {
        return [self::Requested, self::Approved, self::Taken];
    }
}
