<?php

declare(strict_types=1);

use App\Modules\Leave\Http\Controllers\CalendarController;
use App\Modules\Leave\Models\LeaveHoliday;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Leave\Support\CalendarPages;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;

mutates(CalendarController::class, CalendarPages::class);

/** The queries of the calendar, whatever the number of people on the page (the session and the panel frame included). */
const LEAVE_CALENDAR_QUERIES = 20;

beforeEach(function (): void {
    workIn($this->tenant = Tenant::factory()->create(['slug' => 'acme']));
    // Wednesday 4 November 2026, noon in Mexico City.
    $this->travelTo('2026-11-04 18:00:00');
    LeaveSetting::factory()->create(['tenant_id' => $this->tenant->id, 'tracking_since' => '2025-01-01']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    $this->sales = Area::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ventas']);
    $this->ana = leaveCalendarPerson('Ana Solís');
    $this->beto = leaveCalendarPerson('Beto Ruiz');
});

function leaveCalendarPerson(string $name): Employee
{
    return Employee::factory()->create(['tenant_id' => test()->tenant->id, 'name' => $name]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function leaveCalendarRequest(Employee $person, string $state, string $startsOn, string $endsOn, array $attributes = []): LeaveRequest
{
    $factory = LeaveRequest::factory();
    $factory = match ($state) {
        'approved' => $factory->approved(),
        'taken' => $factory->taken(),
        'cancelled' => $factory->cancelled(),
        default => $factory,
    };

    return $factory->create(['tenant_id' => $person->tenant_id, 'employee_id' => $person->id, 'starts_on' => $startsOn, 'ends_on' => $endsOn, ...$attributes]);
}

/**
 * @param  array<string, mixed>  $query
 * @return array<string, mixed>
 */
function leaveCalendarProps(array $query = []): array
{
    return test()->actingAs(test()->hr)->get(route('tenant.vacations.calendar', [test()->tenant, ...$query]))->assertOk()->inertiaProps();
}

it('lives under the vacations of the company and is hidden from a union', function (): void {
    expect(route('tenant.vacations.calendar', $this->tenant, absolute: false))->toBe('/admin/acme/vacations/calendar');

    $union = Tenant::factory()->union()->create();

    $this->actingAs(memberOf($union, TenantRole::Owner))->get(route('tenant.vacations.calendar', $union))->assertNotFound();
});

it('opens on the month of today, local, with its days and its neighbours', function (): void {
    $props = leaveCalendarProps();

    expect($props['month'])->toBe('2026-11')
        ->and($props['prevMonth'])->toBe('2026-10')
        ->and($props['nextMonth'])->toBe('2026-12')
        ->and($props['today'])->toBe('2026-11-04')
        ->and($props['out'])->toHaveCount(30);
});

it('opens on the month asked, and on the current one when it is not a month', function (string $asked, string $month): void {
    expect(leaveCalendarProps(['month' => $asked])['month'])->toBe($month);
})->with([
    'a month' => ['2027-02', '2027-02'],
    'december rolls the year' => ['2026-12', '2026-12'],
    'month 13' => ['2026-13', '2026-11'],
    'month 0' => ['2026-00', '2026-11'],
    'without zero' => ['2026-1', '2026-11'],
    'text' => ['noviembre', '2026-11'],
    'a whole date' => ['2026-12-01', '2026-11'],
    'too far' => ['9999-12', '2026-11'],
]);

it('draws an absence of the people on the page cut to the month', function (): void {
    leaveCalendarRequest($this->ana, 'approved', '2026-10-28', '2026-11-03');
    leaveCalendarRequest($this->ana, 'taken', '2026-11-30', '2026-12-04');

    $ana = collect(leaveCalendarProps()['people']['data'])->firstWhere('name', 'Ana Solís');

    expect($ana['absences'])->toBe([
        ['from' => '2026-11-01', 'to' => '2026-11-03', 'kind' => 'vacation', 'pending' => false],
        ['from' => '2026-11-30', 'to' => '2026-11-30', 'kind' => 'vacation', 'pending' => false],
    ])->and(collect(leaveCalendarProps()['people']['data'])->firstWhere('name', 'Beto Ruiz')['absences'])->toBe([]);
});

it('does not draw what was cancelled, nor a month it does not touch', function (): void {
    leaveCalendarRequest($this->ana, 'cancelled', '2026-11-09', '2026-11-10');
    leaveCalendarRequest($this->ana, 'approved', '2026-12-01', '2026-12-04');

    expect(collect(leaveCalendarProps()['people']['data'])->firstWhere('name', 'Ana Solís')['absences'])->toBe([]);
});

it('counts how many are out each day of the month', function (): void {
    leaveCalendarRequest($this->ana, 'approved', '2026-10-28', '2026-11-03');
    leaveCalendarRequest($this->beto, 'approved', '2026-11-03', '2026-11-04');

    $out = leaveCalendarProps()['out'];

    expect($out[0])->toBe(1)->and($out[2])->toBe(2)->and($out[3])->toBe(1)->and($out[4])->toBe(0)->and(array_sum($out))->toBe(3 + 2);
});

it('counts the days out of everybody the filters leave, not only of the page', function (): void {
    foreach (range(1, 30) as $number) {
        leaveCalendarRequest(leaveCalendarPerson("Persona {$number}"), 'approved', '2026-11-10', '2026-11-10');
    }

    $props = leaveCalendarProps();

    expect($props['people']['total'])->toBe(32)->and($props['people']['data'])->toHaveCount(25)->and($props['out'][9])->toBe(30);
});

it('says vacations or leave, never which leave', function (): void {
    $type = LeaveType::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Maternidad']);
    LeaveRequest::factory()->approved()->ofType($type)->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->beto->id, 'starts_on' => '2026-11-10', 'ends_on' => '2026-11-12', 'working_days' => 3]);
    leaveCalendarRequest($this->ana, 'approved', '2026-11-10', '2026-11-10', ['note' => 'Una nota privada']);

    $props = leaveCalendarProps();
    $beto = collect($props['people']['data'])->firstWhere('name', 'Beto Ruiz');

    expect($beto['absences'][0]['kind'])->toBe('leave')
        ->and(json_encode($props))->not->toContain('Maternidad')->not->toContain('Una nota privada');
});

it('draws what still waits for an answer dotted, only for who manages vacations', function (): void {
    leaveCalendarRequest($this->ana, 'requested', '2026-11-16', '2026-11-17');
    $type = LeaveType::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Maternidad']);
    LeaveRequest::factory()->ofType($type)->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->beto->id, 'starts_on' => '2026-11-18', 'ends_on' => '2026-11-18', 'working_days' => 1]);

    $people = collect(leaveCalendarProps()['people']['data']);

    expect($people->firstWhere('name', 'Ana Solís')['absences'])->toBe([['from' => '2026-11-16', 'to' => '2026-11-17', 'kind' => 'vacation', 'pending' => true]])
        ->and($people->firstWhere('name', 'Beto Ruiz')['absences'][0])->toBe(['from' => '2026-11-18', 'to' => '2026-11-18', 'kind' => 'leave', 'pending' => true]);

    $viewer = memberOf($this->tenant, TenantRole::Viewer);
    $seen = $this->actingAs($viewer)->get(route('tenant.vacations.calendar', $this->tenant))->assertOk()->inertiaProps();

    expect(collect($seen['people']['data'])->pluck('absences')->flatten(1)->all())->toBe([])
        ->and($seen['canManage'])->toBeFalse()
        ->and(json_encode($seen))->not->toContain('Maternidad');
});

it('narrows by work center and by area, the page and the days out', function (): void {
    $joel = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Joel Álvarez', 'work_center_id' => $this->plant->id, 'area_id' => $this->sales->id]);
    leaveCalendarRequest($joel, 'approved', '2026-11-05', '2026-11-05');
    leaveCalendarRequest($this->ana, 'approved', '2026-11-05', '2026-11-05');

    expect(array_column(leaveCalendarProps(['area' => $this->sales->id])['people']['data'], 'name'))->toBe(['Joel Álvarez'])
        ->and(array_column(leaveCalendarProps(['work_center' => $this->plant->id])['people']['data'], 'name'))->toBe(['Joel Álvarez'])
        ->and(leaveCalendarProps()['out'][4])->toBe(2)
        ->and(leaveCalendarProps(['area' => $this->sales->id])['out'][4])->toBe(1)
        ->and(leaveCalendarProps(['area' => $this->sales->id])['filters'])->toBe(['center' => null, 'area' => ['value' => $this->sales->id, 'label' => 'Ventas']])
        ->and(leaveCalendarProps(['work_center' => $this->plant->id])['filters']['center'])->toBe(['value' => $this->plant->id, 'label' => 'Planta Norte']);
});

it('is a 404 for a work center that is not one in use of the company', function (): void {
    $other = Tenant::factory()->create();
    $foreign = WorkCenter::factory()->for($other)->create();
    $this->actingAs($this->hr);

    $this->get(route('tenant.vacations.calendar', [$this->tenant, 'work_center' => $foreign->id]))->assertNotFound();
    $this->get(route('tenant.vacations.calendar', [$this->tenant, 'work_center' => 'no-es-uuid']))->assertNotFound();
});

it('marks the days nobody works: the holidays of the law and the ones of the company', function (): void {
    LeaveHoliday::factory()->create(['tenant_id' => $this->tenant->id, 'date' => '2026-11-12']);
    LeaveHoliday::factory()->create(['tenant_id' => $this->tenant->id, 'date' => '2026-12-01']);

    // The third Monday of November is the one of the Revolution.
    expect(leaveCalendarProps()['holidays'])->toBe(['2026-11-12', '2026-11-16']);
});

it('shows nothing of another company', function (): void {
    $other = Tenant::factory()->create(['slug' => 'otra']);
    LeaveSetting::factory()->create(['tenant_id' => $other->id]);
    $stranger = Employee::factory()->create(['tenant_id' => $other->id, 'name' => 'Extraña']);
    LeaveRequest::factory()->approved()->create(['tenant_id' => $other->id, 'employee_id' => $stranger->id, 'starts_on' => '2026-11-05', 'ends_on' => '2026-11-05']);
    LeaveHoliday::factory()->create(['tenant_id' => $other->id, 'date' => '2026-11-19']);

    $props = leaveCalendarProps();

    expect(array_column($props['people']['data'], 'name'))->not->toContain('Extraña')
        ->and(array_sum($props['out']))->toBe(0)
        ->and($props['holidays'])->not->toContain('2026-11-19');
    $this->get(route('tenant.vacations.calendar', $other))->assertNotFound();
});

it('lets owner, hr and viewer read it and closes it to the rest', function (TenantRole $role, bool $allowed): void {
    $response = $this->actingAs(memberOf($this->tenant, $role))->get(route('tenant.vacations.calendar', $this->tenant));

    $allowed ? $response->assertOk() : $response->assertForbidden();
})->with([
    'owner' => [TenantRole::Owner, true],
    'hr' => [TenantRole::Hr, true],
    'viewer' => [TenantRole::Viewer, true],
    'committee' => [TenantRole::Committee, false],
    'complaints' => [TenantRole::Complaints, false],
]);

it('asks the same queries whatever the number of people on the page', function (int $more): void {
    foreach (range(1, $more) as $number) {
        $person = leaveCalendarPerson("Otra {$number}");
        leaveCalendarRequest($person, 'approved', '2026-11-05', '2026-11-06');
        leaveCalendarRequest($person, 'requested', '2026-11-16', '2026-11-17');
    }
    $this->actingAs($this->hr);

    $this->expectsDatabaseQueryCount(LEAVE_CALENDAR_QUERIES);
    $this->get(route('tenant.vacations.calendar', $this->tenant))->assertOk();
})->with(['a few' => [3], 'a full page' => [23]]);
