<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Data\ZoneReading;
use App\Modules\Attendance\Models\AttendanceZone;
use InvalidArgumentException;

/**
 * Pure geometry: which zone a position falls in (the nearest that contains it, else the nearest overall), how far it is
 * from that zone's center (Haversine, in meters, on a 6,371,000 m sphere) and whether it is inside. Criterio 3.4.
 */
final class Geofence
{
    private const float EARTH_RADIUS_M = 6371000.0;

    /**
     * @param  array<array-key, AttendanceZone>  $zones
     *
     * @throws InvalidArgumentException when the coordinates cannot exist
     */
    public static function evaluate(array $zones, float $lat, float $lng, float $accuracyM, int $maxAccuracyM): ZoneReading
    {
        if (! is_finite($lat) || ! is_finite($lng) || abs($lat) > 90 || abs($lng) > 180) {
            throw new InvalidArgumentException('The coordinates are out of range.');
        }

        $accuracyOk = $accuracyM <= $maxAccuracyM;
        $nearest = null;
        $nearestDistance = INF;
        $inside = false;

        // A zone that contains the point wins over a nearer one that does not (a point in a large yard next to a small
        // booth is in the yard); among equals the nearest wins, and on a tie the first given. Only when no zone contains
        // the point is the nearest overall returned, outside.
        foreach ($zones as $zone) {
            $distance = self::distance($lat, $lng, $zone->latitude, $zone->longitude);
            $contains = $distance <= $zone->radius_m;

            if (($contains && ! $inside) || ($contains === $inside && $distance < $nearestDistance)) {
                $nearest = $zone;
                $nearestDistance = $distance;
                $inside = $contains;
            }
        }

        if ($nearest === null) {
            return new ZoneReading(null, null, false, $accuracyOk);
        }

        return new ZoneReading($nearest->id, $nearestDistance, $inside, $accuracyOk);
    }

    private static function distance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * self::EARTH_RADIUS_M * asin(min(1.0, sqrt($a)));
    }
}
