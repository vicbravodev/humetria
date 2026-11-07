<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Data\ZoneReading;
use App\Modules\Attendance\Models\AttendanceZone;
use InvalidArgumentException;

/**
 * Pure geometry: how far a position is from the nearest zone (Haversine, in meters, on a 6,371,000 m sphere) and whether
 * it is inside. Criterio 3.4.
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

        foreach ($zones as $zone) {
            $distance = self::distance($lat, $lng, $zone->latitude, $zone->longitude);
            if ($distance < $nearestDistance) {
                $nearest = $zone;
                $nearestDistance = $distance;
            }
        }

        if ($nearest === null) {
            return new ZoneReading(null, null, false, $accuracyOk);
        }

        return new ZoneReading($nearest->id, $nearestDistance, $nearestDistance <= $nearest->radius_m, $accuracyOk);
    }

    private static function distance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * self::EARTH_RADIUS_M * asin(min(1.0, sqrt($a)));
    }
}
