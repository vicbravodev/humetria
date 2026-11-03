<?php

declare(strict_types=1);

namespace App\Modules\Seating\Enums;

/**
 * The state of the report of a damaged seat: open until RH writes how it was resolved.
 */
enum ReportStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Abierto',
            self::Resolved => 'Resuelto',
        };
    }
}
