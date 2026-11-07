<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Attendance\Actions\SavePolicy;
use App\Modules\Attendance\Actions\SaveSchedule;
use App\Modules\Attendance\Enums\OutOfZoneRule;
use App\Modules\Attendance\Enums\ShiftType;
use App\Modules\Attendance\Enums\WorkMode;
use App\Modules\Attendance\Models\AttendancePolicy;
use App\Modules\Attendance\Models\AttendanceSchedule;
use App\Modules\Tenancy\Models\Tenant;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

mutates(SaveSchedule::class, SavePolicy::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
});

it('starts a company\'s policy blocking out of zone, at 100 m, with the QR on site and the trusted browser, kept 12 months', function (): void {
    $policy = AttendancePolicy::query()->create(['tenant_id' => $this->tenant->id])->fresh();

    expect($policy)
        ->out_of_zone->toBe(OutOfZoneRule::Block)
        ->max_accuracy_m->toBe(100)
        ->require_qr_onsite->toBeTrue()
        ->trusted_may_use_browser->toBeTrue()
        ->retention_months->toBe(12);
});

it('saves the one policy of the company, to the audit trail and the log', function (): void {
    Log::spy();

    resolve(SavePolicy::class)->handle(OutOfZoneRule::AllowAndFlag, 50, false, false, 24);
    $policy = resolve(SavePolicy::class)->handle(OutOfZoneRule::Block, 80, true, false, 18);

    expect(AttendancePolicy::query()->sole())
        ->id->toBe($policy->id)
        ->out_of_zone->toBe(OutOfZoneRule::Block)
        ->max_accuracy_m->toBe(80)
        ->require_qr_onsite->toBeTrue()
        ->trusted_may_use_browser->toBeFalse()
        ->retention_months->toBe(18)
        ->and(AuditEntry::query()->where('event', 'attendance.policy_saved')->count())->toBe(2);
    Log::shouldHaveReceived('info')->twice()->with('attendance.policy_saved', ['policy_id' => $policy->id]);
});

it('keeps the records at least 12 months (criterio 7.1)', function (int $months): void {
    expect(fn () => resolve(SavePolicy::class)->handle(OutOfZoneRule::Block, 100, true, true, $months))
        ->toThrow(QueryException::class, 'attendance_policies_retention_at_least_12');
})->with([11, 0]);

it('saves the agreed weekly schedule of a person, one per person', function (): void {
    Log::spy();
    $ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id]);
    $week = [1 => 480, 2 => 480, 3 => 480, 4 => 480, 5 => 480, 6 => 0, 7 => 0];

    resolve(SaveSchedule::class)->handle($ana->id, ShiftType::Day, WorkMode::Onsite, $week);
    $schedule = resolve(SaveSchedule::class)->handle($ana->id, ShiftType::Night, WorkMode::Remote, array_replace($week, [6 => 420]));

    expect(AttendanceSchedule::query()->sole())
        ->id->toBe($schedule->id)
        ->employee_id->toBe($ana->id)
        ->shift_type->toBe(ShiftType::Night)
        ->work_mode->toBe(WorkMode::Remote)
        ->minutes_by_weekday->toBe([1 => 480, 2 => 480, 3 => 480, 4 => 480, 5 => 480, 6 => 420, 7 => 0]);
    Log::shouldHaveReceived('info')->twice()->with('attendance.schedule_saved', ['schedule_id' => $schedule->id, 'employee_id' => $ana->id]);
});

it('saves no schedule for a person of another company', function (): void {
    $other = EmployeeFactory::new()->create();

    expect(fn () => resolve(SaveSchedule::class)->handle($other->id, ShiftType::Day, WorkMode::Onsite, [1 => 480]))->toThrow(InvalidArgumentException::class)
        ->and(AttendanceSchedule::query()->withoutGlobalScopes()->count())->toBe(0);
});
