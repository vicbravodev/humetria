<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Enums;

/**
 * What bounded the time of a mark made without a connection (criterio 3.3.1): a label, not a branch of the calculation.
 * The time itself always comes from the one rule of the delivery.
 */
enum TimeBasis: string
{
    /** A valid and recent QR proved the mark was not before the start of its window. */
    case QrWindow = 'qr_window';
    /** Only the ticket, the neighbouring marks and the receipt bound the time the person declared. */
    case DeclaredInterval = 'declared_interval';

    public function method(): MarkMethod
    {
        return match ($this) {
            self::QrWindow => MarkMethod::OfflineQr,
            self::DeclaredInterval => MarkMethod::OfflineDeclared,
        };
    }
}
