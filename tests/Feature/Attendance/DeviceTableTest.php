<?php

declare(strict_types=1);

use App\Modules\Attendance\Models\AttendanceDevice;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
});

it('does not delete a device a mark points to', function (): void {
    $device = AttendanceDevice::factory()->create(['tenant_id' => $this->tenant->id]);
    AttendanceMark::factory()->create(['tenant_id' => $this->tenant->id, 'device_id' => $device->id]);

    expect(fn () => $device->delete())->toThrow(QueryException::class);
});

it('does not let a mark point to a device that does not exist or is of another company', function (): void {
    $deviceOfB = AttendanceDevice::factory()->create();

    expect(fn () => AttendanceMark::factory()->create(['tenant_id' => $this->tenant->id, 'device_id' => $deviceOfB->id]))->toThrow(QueryException::class)
        ->and(fn () => AttendanceMark::factory()->create(['tenant_id' => $this->tenant->id, 'device_id' => (string) Str::uuid()]))->toThrow(QueryException::class);
});

it('keeps one device per key, and a counter a 32-bit authenticator can reach', function (): void {
    $device = AttendanceDevice::factory()->create(['tenant_id' => $this->tenant->id, 'sign_count' => 4294967295]);

    expect(fn () => AttendanceDevice::factory()->create(['tenant_id' => $this->tenant->id, 'credential_id' => $device->credential_id]))->toThrow(QueryException::class)
        ->and(fn () => AttendanceDevice::factory()->create(['tenant_id' => $this->tenant->id, 'sign_count' => 4294967296]))->toThrow(QueryException::class)
        ->and(fn () => AttendanceDevice::factory()->create(['tenant_id' => $this->tenant->id, 'label' => ' ']))->toThrow(QueryException::class);
});

it('never shows the key of a device when it is turned into an array', function (): void {
    $device = AttendanceDevice::factory()->create(['tenant_id' => $this->tenant->id]);

    expect($device->toArray())->not->toHaveKeys(['credential_id', 'public_key']);
});
