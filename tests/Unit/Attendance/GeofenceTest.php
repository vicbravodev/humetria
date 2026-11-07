<?php

declare(strict_types=1);

use App\Modules\Attendance\Data\ZoneReading;
use App\Modules\Attendance\Models\AttendanceZone;
use App\Modules\Attendance\Support\Geofence;

mutates(Geofence::class);

function zone(string $id, float $lat, float $lng, int $radius): AttendanceZone
{
    return (new AttendanceZone)->forceFill(['id' => $id, 'latitude' => $lat, 'longitude' => $lng, 'radius_m' => $radius]);
}

/** Meters of latitude per degree on the 6371000 m sphere. */
const METERS_PER_DEGREE = 6371000 * M_PI / 180;

it('measures 100 m between two points a hundred meters apart (Haversine)', function (): void {
    $reading = Geofence::evaluate([zone('a', 19.0, -99.0, 5000)], 19.0 + 100 / METERS_PER_DEGREE, -99.0, 10, 100);

    expect($reading->distanceM)->toBeFloat()->toEqualWithDelta(100.0, 0.01);
});

it('measures along a parallel with the cosine of the latitude', function (): void {
    $lat = 60.0;
    $reading = Geofence::evaluate([zone('a', $lat, 10.0, 5000)], $lat, 10.0 + 100 / (METERS_PER_DEGREE * cos(deg2rad($lat))), 10, 100);

    expect($reading->distanceM)->toEqualWithDelta(100.0, 0.05);
});

it('is inside a 50 m zone at 49.9 m and outside at 50.1 m', function (): void {
    $zones = [zone('a', 19.0, -99.0, 50)];

    $near = Geofence::evaluate($zones, 19.0 + 49.9 / METERS_PER_DEGREE, -99.0, 10, 100);
    $far = Geofence::evaluate($zones, 19.0 + 50.1 / METERS_PER_DEGREE, -99.0, 10, 100);

    expect($near->inside)->toBeTrue()->and($near->zoneId)->toBe('a')
        ->and($far->inside)->toBeFalse()->and($far->zoneId)->toBe('a');
});

it('is inside exactly on the center (distance 0)', function (): void {
    $reading = Geofence::evaluate([zone('a', 19.0, -99.0, 10)], 19.0, -99.0, 10, 100);

    expect($reading->inside)->toBeTrue()->and($reading->distanceM)->toBe(0.0);
});

it('picks the nearest zone', function (): void {
    $zones = [zone('almacen', 19.0010, -99.0, 500), zone('edificio-a', 19.0001, -99.0, 500)];

    expect(Geofence::evaluate($zones, 19.0, -99.0, 10, 100)->zoneId)->toBe('edificio-a')
        ->and(Geofence::evaluate(array_reverse($zones), 19.0, -99.0, 10, 100)->zoneId)->toBe('edificio-a');
});

it('has no zone, distance or inside without zones', function (): void {
    expect(Geofence::evaluate([], 19.0, -99.0, 10, 100))->toEqual(new ZoneReading(null, null, false, true));
});

it('trusts the accuracy up to the maximum and not beyond', function (): void {
    $zones = [zone('a', 19.0, -99.0, 50)];

    expect(Geofence::evaluate($zones, 19.0, -99.0, 100, 100)->accuracyOk)->toBeTrue()
        ->and(Geofence::evaluate($zones, 19.0, -99.0, 101, 100)->accuracyOk)->toBeFalse();
});

it('rejects coordinates that cannot exist', function (float $lat, float $lng): void {
    expect(fn () => Geofence::evaluate([zone('a', 19.0, -99.0, 50)], $lat, $lng, 10, 100))->toThrow(InvalidArgumentException::class);
})->with([
    'lat 91' => [91.0, 0.0],
    'lat -91' => [-91.0, 0.0],
    'lng 181' => [0.0, 181.0],
    'lng -181' => [0.0, -181.0],
    'lat NaN' => [NAN, 0.0],
    'lng INF' => [0.0, INF],
]);

it('accepts the extremes of the ranges', function (float $lat, float $lng): void {
    expect(Geofence::evaluate([], $lat, $lng, 10, 100)->inside)->toBeFalse();
})->with([[90.0, 180.0], [-90.0, -180.0]]);
