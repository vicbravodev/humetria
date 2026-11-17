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

    public function evidence(): EvidenceLevel
    {
        return match ($this) {
            // Strong is complete for the center's mode: QR and zone on site, a good position in the field.
            self::QrAndGeofence, self::Field => EvidenceLevel::Strong,
            self::Geofence, self::Qr, self::FieldUnlocated => EvidenceLevel::Medium,
            self::Remote, self::TrustedBrowser => EvidenceLevel::Basic,
            self::Correction => EvidenceLevel::Corrected,
        };
    }
}
