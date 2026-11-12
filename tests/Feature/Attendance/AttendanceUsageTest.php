<?php

declare(strict_types=1);

use App\Modules\Attendance\Contracts\AttendanceUsage;
use App\Modules\Attendance\Models\AttendanceDevice;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\AttendancePolicy;
use App\Modules\Attendance\Models\KioskDevice;
use App\Modules\Attendance\Support\CompanyAttendanceUsage;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Organization\EmployeeFactory;

mutates(CompanyAttendanceUsage::class);

it('tells which companies record working time with Attendance: a policy, a kiosk, a linked phone or a mark', function (Closure $setup): void {
    $uses = Tenant::factory()->create();
    $idle = Tenant::factory()->create();
    $setup($uses);

    expect(resolve(AttendanceUsage::class)->among([$uses->id, $idle->id]))->toBe([$uses->id]);
})->with([
    'a policy' => [fn (Tenant $tenant) => AttendancePolicy::factory()->create(['tenant_id' => $tenant->id])],
    'a kiosk' => [fn (Tenant $tenant) => KioskDevice::factory()->create(['tenant_id' => $tenant->id])],
    'a linked phone' => [function (Tenant $tenant): void {
        $center = WorkCenter::factory()->for($tenant)->create();
        $person = EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id]);
        AttendanceDevice::factory()->create(['tenant_id' => $tenant->id, 'employee_id' => $person->id]);
    }],
    'a mark' => [fn (Tenant $tenant) => AttendanceMark::factory()->create(['tenant_id' => $tenant->id])],
]);

it('answers each company once, however many things it has, and only those it was asked about', function (): void {
    $busy = Tenant::factory()->create();
    $other = Tenant::factory()->create();
    AttendancePolicy::factory()->create(['tenant_id' => $busy->id]);
    KioskDevice::factory()->count(2)->create(['tenant_id' => $busy->id]);
    AttendancePolicy::factory()->create(['tenant_id' => $other->id]);

    expect(resolve(AttendanceUsage::class)->among([$busy->id]))->toBe([$busy->id])
        ->and(resolve(AttendanceUsage::class)->among([]))->toBe([]);
});

it('does not need to work inside a company, and does not enter one', function (): void {
    $tenant = Tenant::factory()->create();
    AttendancePolicy::factory()->create(['tenant_id' => $tenant->id]);

    expect(resolve(AttendanceUsage::class)->among([$tenant->id]))->toBe([$tenant->id])
        ->and(fn () => resolve(App\Modules\Tenancy\Contracts\CurrentTenant::class)->id())->toThrow(App\Modules\Tenancy\Exceptions\NoCurrentTenant::class);
});
