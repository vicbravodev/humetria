<?php

declare(strict_types=1);

use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Support\AbsentDays;
use App\Modules\Attendance\Support\PersonWeek;
use App\Modules\Attendance\Support\WeekPeriod;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Inertia\Testing\AssertableInertia;

mutates(AbsentDays::class);

beforeEach(function (): void {
    // Wednesday 11 November 2026, 12:00 in Mexico City: the last week that ended is 2 to 8 November.
    $this->travelTo(CarbonImmutable::parse('2026-11-11 18:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    LeaveSetting::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create();
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'name' => 'Ana López']);
    $this->bob = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'name' => 'Bob Díaz']);
});

/**
 * Approved vacations of a person, from one day to another.
 *
 * @param  array<string, mixed>  $attributes
 */
function awayFrom(Employee $person, string $from, string $to, array $attributes = []): LeaveRequest
{
    return LeaveRequest::factory()->approved()->create(['tenant_id' => $person->tenant_id, 'employee_id' => $person->id, 'starts_on' => $from, 'ends_on' => $to, 'working_days' => 1, ...$attributes]);
}

/**
 * The days of «Mi semana» of a person, for the week of 2 to 8 November 2026 unless another is asked.
 *
 * @return list<array<string, mixed>>
 */
function weekDaysOf(Employee $person, string $week = '2026-11-02'): array
{
    return PersonWeek::of($person->id, 'Ana López', WeekPeriod::ofDate(CarbonImmutable::parse($week)), 'acme')['days'];
}

/**
 * @return array<string, list<int>> the absent days of each person of RH's overview, by name
 */
function overviewAbsentDays(): array
{
    test()->actingAs(memberOf(test()->tenant, TenantRole::Hr));
    $people = test()->get(route('tenant.attendance.index', 'acme'))->assertOk()->inertiaProps('people.data');

    return array_column($people, 'absentDays', 'name');
}

it('reads a day of approved vacations with no marks as vacations, and the other days as before', function (): void {
    awayFrom($this->ana, '2026-11-03', '2026-11-03');

    expect(array_column(weekDaysOf($this->ana), 'absence', 'date'))->toBe([
        '2026-11-02' => null, '2026-11-03' => 'vacation', '2026-11-04' => null, '2026-11-05' => null, '2026-11-06' => null, '2026-11-07' => null, '2026-11-08' => null,
    ])->and(weekDaysOf($this->ana)[1]['marks'])->toBe([]);
});

it('keeps the marks and the hours of a day worked while away: the mark rules, and the day still says the absence', function (): void {
    awayFrom($this->ana, '2026-11-03', '2026-11-03');

    foreach ([[MarkType::Start, '2026-11-03 08:00'], [MarkType::End, '2026-11-03 12:00']] as [$type, $local]) {
        AttendanceMark::factory()->create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'work_center_id' => $this->plant->id, 'type' => $type,
            'marked_at' => CarbonImmutable::parse($local, 'America/Mexico_City')->utc(),
        ]);
    }

    $tuesday = weekDaysOf($this->ana)[1];
    expect($tuesday['marks'])->toHaveCount(2)
        ->and([$tuesday['workedMinutes'], $tuesday['absence']])->toBe([240, 'vacation']);
});

it('says leave for any leave and never its type', function (): void {
    $maternity = LeaveType::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Maternidad']);
    LeaveRequest::factory()->ofType($maternity)->approved()->create(['employee_id' => $this->ana->id, 'starts_on' => '2026-11-05', 'ends_on' => '2026-11-06', 'working_days' => 2]);

    $week = PersonWeek::of($this->ana->id, 'Ana López', WeekPeriod::ofDate(CarbonImmutable::parse('2026-11-02')), 'acme');

    expect(array_column($week['days'], 'absence'))->toBe([null, null, null, 'leave', 'leave', null, null])
        ->and(json_encode($week, JSON_THROW_ON_ERROR))->not->toContain('Maternidad');
});

it('marks only the days of the week an absence covers, when it starts before or ends after it', function (): void {
    awayFrom($this->ana, '2026-10-26', '2026-11-03');
    awayFrom($this->ana, '2026-11-08', '2026-11-13');

    expect(array_column(weekDaysOf($this->ana), 'absence'))->toBe(['vacation', 'vacation', null, null, null, null, 'vacation'])
        ->and(array_column(weekDaysOf($this->ana, '2026-11-09'), 'absence'))->toBe(['vacation', 'vacation', 'vacation', 'vacation', 'vacation', null, null]);
});

it('counts only what was approved, of that person', function (): void {
    LeaveRequest::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'starts_on' => '2026-11-03', 'ends_on' => '2026-11-03', 'working_days' => 1]);
    awayFrom($this->bob, '2026-11-04', '2026-11-04');

    expect(array_column(weekDaysOf($this->ana), 'absence'))->toBe([null, null, null, null, null, null, null]);
});

it('tells RH which days of the week each person of the page was away, Monday first', function (): void {
    awayFrom($this->ana, '2026-11-03', '2026-11-04');
    awayFrom($this->ana, '2026-11-08', '2026-11-20');

    expect(overviewAbsentDays())->toBe(['Ana López' => [1, 2, 6], 'Bob Díaz' => []]);
});

it('never tells RH\'s overview the dates nor the kind of an absence', function (): void {
    $maternity = LeaveType::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Maternidad']);
    LeaveRequest::factory()->ofType($maternity)->approved()->create(['employee_id' => $this->ana->id, 'starts_on' => '2026-11-05', 'ends_on' => '2026-11-06', 'working_days' => 2]);
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));

    $this->get(route('tenant.attendance.index', 'acme'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('people.data.0.absentDays', [3, 4])
        ->where('people.data', fn ($people): bool => ! str_contains(json_encode($people, JSON_THROW_ON_ERROR), 'Maternidad') && ! str_contains(json_encode($people, JSON_THROW_ON_ERROR), 'leave')));
});

it('leaves everything as it was in a company that does not use Vacaciones', function (): void {
    LeaveSetting::query()->delete();

    expect(array_column(weekDaysOf($this->ana), 'absence'))->toBe([null, null, null, null, null, null, null])
        ->and(overviewAbsentDays())->toBe(['Ana López' => [], 'Bob Díaz' => []]);
});
