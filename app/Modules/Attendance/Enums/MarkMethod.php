<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Enums;

/** How a mark was made (criterios 4.2 to 4.4 and 5.3). */
enum MarkMethod: string
{
    case QrAndGeofence = 'qr_and_geofence';
    case Geofence = 'geofence';
    case Qr = 'qr';
    case Remote = 'remote';
    case TrustedBrowser = 'trusted_browser';
    case Correction = 'correction';
    /** In a field center (CenterMode::Field), with a position within the policy's accuracy, wherever it was taken. */
    case Field = 'field';
    /** In a field center, with no position or one less accurate than the policy asks (it is kept all the same). */
    case FieldUnlocated = 'field_unlocated';
    /** Made without a connection with a QR that proved the mark was not before its window (criterios 3.3.1 and 5.3): a lower bound, not an exact time. */
    case OfflineQr = 'offline_qr';
    /** Made without a connection, its time declared by the person and bounded only by the ticket and the neighbouring marks. */
    case OfflineDeclared = 'offline_declared';

    public function evidence(): EvidenceLevel
    {
        return match ($this) {
            // Strong is complete for the center's mode: QR and zone on site, a good position in the field.
            self::QrAndGeofence, self::Field => EvidenceLevel::Strong,
            self::Geofence, self::Qr, self::FieldUnlocated, self::OfflineQr => EvidenceLevel::Medium,
            self::Remote, self::TrustedBrowser, self::OfflineDeclared => EvidenceLevel::Basic,
            self::Correction => EvidenceLevel::Corrected,
        };
    }
}
