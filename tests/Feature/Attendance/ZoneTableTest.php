<?php

declare(strict_types=1);

use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\AttendanceZone;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\QueryException;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
});

it('does not read the zones of another company with a query', function (): void {
    $b = Tenant::factory()->create();
    $zoneOfB = AttendanceZone::factory()->create(['tenant_id' => $b->id]);

    expect(AttendanceZone::query()->count())->toBe(0)
        ->and(AttendanceZone::query()->find($zoneOfB->id))->toBeNull();

    workIn($b);
    expect(AttendanceZone::query()->count())->toBe(1);
});

it('rejects a radius, latitude or longitude out of range', function (array $values): void {
    expect(fn () => AttendanceZone::factory()->create(['tenant_id' => $this->tenant->id, ...$values]))
        ->toThrow(QueryException::class);
})->with([
    'radius 9' => [['radius_m' => 9]],
    'radius 5001' => [['radius_m' => 5001]],
    'latitude 91' => [['latitude' => 91.0]],
    'longitude -181' => [['longitude' => -181.0]],
]);

it('accepts the edges of the ranges', function (): void {
    AttendanceZone::factory()->create(['tenant_id' => $this->tenant->id, 'radius_m' => 10, 'latitude' => -90.0, 'longitude' => -180.0]);
    AttendanceZone::factory()->create(['tenant_id' => $this->tenant->id, 'radius_m' => 5000, 'latitude' => 90.0, 'longitude' => 180.0]);

    expect(AttendanceZone::query()->count())->toBe(2);
});

it('does not delete a zone a mark points to', function (): void {
    $zone = AttendanceZone::factory()->create(['tenant_id' => $this->tenant->id]);
    AttendanceMark::factory()->create(['tenant_id' => $this->tenant->id, 'zone_id' => $zone->id]);

    expect(fn () => $zone->delete())->toThrow(QueryException::class);
});
