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

    public function evidence(): EvidenceLevel
    {
        return match ($this) {
            self::QrAndGeofence => EvidenceLevel::Strong,
            self::Geofence, self::Qr => EvidenceLevel::Medium,
            self::Remote, self::TrustedBrowser => EvidenceLevel::Basic,
            self::Correction => EvidenceLevel::Corrected,
        };
    }
}
