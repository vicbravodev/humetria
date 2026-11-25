<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Enums;

use App\Modules\Attendance\Data\ZoneReading;

/**
 * Where a mark made without a connection stood against the zones of its center, only to flag it (criterio 4.2): it never
 * blocks the mark. `Unknown` lights `no_position`, `Outside` lights `outside_zone` and `NoZones` (a center with none
 * configured) lights `zones_not_configured`.
 */
enum OfflinePosition
{
    case Unknown;
    case Inside;
    case Outside;
    case NoZones;

    /** `null` is no position at all; otherwise inside or outside by what the geofence read. */
    public static function fromReading(?ZoneReading $reading): self
    {
        return match (true) {
            $reading === null => self::Unknown,
            $reading->inside => self::Inside,
            default => self::Outside,
        };
    }
}
