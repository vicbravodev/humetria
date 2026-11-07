<?php

declare(strict_types=1);

use App\Modules\Attendance\Actions\AcceptAgreement;
use App\Modules\Attendance\Models\AgreementAcceptance;
use App\Modules\Attendance\Models\AttendanceAgreement;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\AttendancePolicy;
use App\Modules\Attendance\Models\AttendanceSchedule;
use App\Modules\Attendance\Models\KioskDevice;
use App\Modules\Tenancy\Models\Tenant;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Str;

/*
 * Company A reads none of company B's attendance data. By URL it is pinned when the panel pages exist.
 */
it('does not read the marks of another company with a query', function (): void {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();
    $markOfB = AttendanceMark::factory()->create(['tenant_id' => $b->id]);

    workIn($a);
    expect(AttendanceMark::query()->count())->toBe(0)
        ->and(AttendanceMark::query()->find($markOfB->id))->toBeNull();

    workIn($b);
    expect(AttendanceMark::query()->count())->toBe(1);
});

it('does not read the schedules or the policy of another company with a query', function (): void {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();
    AttendanceSchedule::factory()->create(['tenant_id' => $b->id]);
    AttendancePolicy::factory()->create(['tenant_id' => $b->id]);

    workIn($a);
    expect(AttendanceSchedule::query()->count())->toBe(0)
        ->and(AttendancePolicy::query()->count())->toBe(0);

    workIn($b);
    expect(AttendanceSchedule::query()->count())->toBe(1)
        ->and(AttendancePolicy::query()->count())->toBe(1);
});

it('does not read the QR screens of another company with a query', function (): void {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();
    $kioskOfB = KioskDevice::factory()->create(['tenant_id' => $b->id]);

    workIn($a);
    expect(KioskDevice::query()->find($kioskOfB->id))->toBeNull();

    workIn($b);
    expect(KioskDevice::query()->count())->toBe(1);
});

it('does not read the agreement or the acceptances of another company with a query', function (): void {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();
    workIn($b);
    $ana = EmployeeFactory::new()->create(['tenant_id' => $b->id]);
    resolve(AcceptAgreement::class)->handle($ana->id, (string) Str::uuid(), 1);

    workIn($a);
    expect(AttendanceAgreement::query()->count())->toBe(0)
        ->and(AgreementAcceptance::query()->count())->toBe(0);

    workIn($b);
    expect(AttendanceAgreement::query()->count())->toBe(1)
        ->and(AgreementAcceptance::query()->count())->toBe(1);
});
