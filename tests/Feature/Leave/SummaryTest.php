<?php

declare(strict_types=1);

use App\Modules\Leave\Http\Controllers\VacationsController;
use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveEntry;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Leave\Models\LeaveStatement;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Leave\Support\SummaryPages;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\ComplianceRoutes;
use Inertia\Testing\AssertableInertia as Assert;

mutates(VacationsController::class, SummaryPages::class);

/** The queries of the overview, whatever the number of people, requests or periods. */
const LEAVE_SUMMARY_QUERIES = 39;

beforeEach(function (): void {
    workIn($this->tenant = Tenant::factory()->create(['slug' => 'acme']));
    // Wednesday 4 November 2026, noon in Mexico City: the week is 2 to 8 November.
    $this->travelTo('2026-11-04 18:00:00');
    LeaveSetting::factory()->create(['tenant_id' => $this->tenant->id, 'tracking_since' => '2025-01-01']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->ana = leaveSummaryPerson('Ana Solís');
    $this->beto = leaveSummaryPerson('Beto Ruiz');
    $this->caro = leaveSummaryPerson('Caro Luz');
    $this->dan = leaveSummaryPerson('Dan Gil');
    $this->eva = leaveSummaryPerson('Eva Paz');
});

function leaveSummaryPerson(string $name): Employee
{
    return Employee::factory()->create(['tenant_id' => test()->tenant->id, 'name' => $name, 'hired_on' => '2020-01-15']);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function leaveSummaryRequest(Employee $person, string $state, string $startsOn, string $endsOn, array $attributes = []): LeaveRequest
{
    $factory = LeaveRequest::factory();
    $factory = match ($state) {
        'approved' => $factory->approved(),
        'taken' => $factory->taken(),
        'cancelled' => $factory->cancelled(),
        'rejected' => $factory->rejected(),
        'escalated' => $factory->escalated(),
        default => $factory,
    };

    return $factory->create(['tenant_id' => $person->tenant_id, 'employee_id' => $person->id, 'starts_on' => $startsOn, 'ends_on' => $endsOn, ...$attributes]);
}

function leaveSummaryPeriod(Employee $person, string $anniversary, int $left, int $years = 1): LeaveEntitlement
{
    return LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $person->tenant_id, 'employee_id' => $person->id, 'years_of_service' => $years, 'anniversary_on' => $anniversary, 'statutory_days' => $left]);
}

/** @return array<string, mixed> */
function leaveSummaryOf(): array
{
    return test()->actingAs(test()->hr)->get(route('tenant.vacations.index', test()->tenant))->assertOk()->inertiaProps()['summary'];
}

it('lives under the vacations of the company and is hidden from a union', function (): void {
    expect(route('tenant.vacations.index', $this->tenant, absolute: false))->toBe('/admin/acme/vacations')
        ->and(ComplianceRoutes::patterns())->toContain('tenant.vacations.*');

    $union = Tenant::factory()->union()->create();

    $this->actingAs(memberOf($union, TenantRole::Owner))->get(route('tenant.vacations.index', $union))->assertNotFound();
});

it('counts who is out today and who is out this week, Monday to Sunday, once each', function (): void {
    leaveSummaryRequest($this->ana, 'approved', '2026-11-03', '2026-11-05');
    leaveSummaryRequest($this->ana, 'approved', '2026-11-05', '2026-11-05', ['working_days' => 1]);
    leaveSummaryRequest($this->beto, 'approved', '2026-11-06', '2026-11-07');
    leaveSummaryRequest($this->caro, 'taken', '2026-11-02', '2026-11-02');
    // Not out: next week, the week before, not answered yes, cancelled.
    leaveSummaryRequest($this->dan, 'approved', '2026-11-09', '2026-11-10');
    leaveSummaryRequest($this->dan, 'approved', '2026-10-26', '2026-10-30');
    leaveSummaryRequest($this->eva, 'cancelled', '2026-11-04', '2026-11-04');
    leaveSummaryRequest($this->eva, 'rejected', '2026-11-04', '2026-11-04');
    leaveSummaryRequest($this->eva, 'requested', '2026-11-04', '2026-11-04');

    expect(leaveSummaryOf())->toMatchArray(['outToday' => 1, 'outThisWeek' => 3]);
});

it('counts requests that wait for the manager apart from those that reached RH', function (): void {
    leaveSummaryRequest($this->ana, 'requested', '2026-12-01', '2026-12-02');
    leaveSummaryRequest($this->beto, 'escalated', '2026-12-01', '2026-12-02');
    leaveSummaryRequest($this->caro, 'approved', '2026-12-01', '2026-12-02');
    leaveSummaryRequest($this->dan, 'rejected', '2026-12-01', '2026-12-02');

    expect(leaveSummaryOf())->toMatchArray(['pending' => 1, 'escalated' => 1]);
});

it('counts the windows about to close, those that closed, and the days that could prescribe, which stay in the balance', function (): void {
    // Prescribable: its eighteen months ended on 15 September 2026. Its window closed long ago.
    leaveSummaryPeriod($this->ana, '2025-03-15', 4);
    // Closed on 15 September 2026, still within the eighteen months.
    leaveSummaryPeriod($this->beto, '2026-03-15', 5);
    // Closes in exactly 60 days (3 January 2027): about to close. One day later is not.
    leaveSummaryPeriod($this->caro, '2026-07-03', 6);
    leaveSummaryPeriod($this->dan, '2026-07-04', 7);
    // Prescribes today, not before today: it is not prescribable yet.
    leaveSummaryPeriod($this->eva, '2025-05-04', 3);
    // Nothing left: it is in no count.
    LeaveEntry::factory()->of(leaveSummaryPeriod($this->eva, '2024-01-10', 2, 2))->taken(2)->create();

    expect(leaveSummaryOf())->toMatchArray(['windowsClosing' => 4, 'prescribableDays' => 4, 'liabilityDays' => 25]);
});

it('counts the people with no start date and the statements nobody has confirmed', function (): void {
    Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Sin Fecha', 'hired_on' => null]);
    $confirmed = leaveSummaryPeriod($this->ana, '2025-03-15', 4);
    LeaveStatement::factory()->create(['tenant_id' => $this->tenant->id, 'leave_entitlement_id' => $confirmed->id, 'acknowledged_at' => '2025-03-20 18:00:00']);
    LeaveStatement::factory()->create(['tenant_id' => $this->tenant->id, 'leave_entitlement_id' => leaveSummaryPeriod($this->beto, '2025-03-15', 4)->id]);

    expect(leaveSummaryOf())->toMatchArray(['missingStartDate' => 1, 'statementsUnacknowledged' => 1]);
});

it('lists who is out today as a page, with vacations or leave and nothing more', function (): void {
    leaveSummaryRequest($this->ana, 'approved', '2026-11-03', '2026-11-05', ['working_days' => 3]);
    $type = LeaveType::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Maternidad']);
    LeaveRequest::factory()->approved()->ofType($type)->create(['employee_id' => $this->beto->id, 'starts_on' => '2026-11-04', 'ends_on' => '2026-11-06', 'working_days' => 3, 'note' => 'Nota secreta']);
    leaveSummaryRequest($this->caro, 'approved', '2026-11-09', '2026-11-10');

    $response = $this->actingAs($this->hr)->get(route('tenant.vacations.index', $this->tenant))->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('leave/index')
        ->where('view', 'out')
        ->where('people.total', 2)
        ->where('people.per_page', 10)
        ->where('people.data.0.name', 'Ana Solís')
        ->where('people.data.0.label', 'Vacaciones')
        ->where('people.data.0.startsOn', '2026-11-03')
        ->where('people.data.0.endsOn', '2026-11-05')
        ->where('people.data.0.days', 3)
        ->where('people.data.1.name', 'Beto Ruiz')
        ->where('people.data.1.label', 'Permiso'));

    expect((string) json_encode($response->inertiaProps(), JSON_UNESCAPED_UNICODE))->not->toContain('Maternidad', 'Nota secreta', 'Cierre de mes');
});

it('lists the windows about to close, the oldest first, and the days that could prescribe', function (): void {
    leaveSummaryPeriod($this->beto, '2026-03-15', 5);
    leaveSummaryPeriod($this->ana, '2025-03-15', 4);
    leaveSummaryPeriod($this->dan, '2026-07-04', 7);

    $this->actingAs($this->hr)->get(route('tenant.vacations.index', [$this->tenant, 'view' => 'windows']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('view', 'windows')
            ->where('people.total', 2)
            ->where('people.data.0.name', 'Ana Solís')
            ->where('people.data.0.label', 'Periodo 2025')
            ->where('people.data.0.days', 4)
            ->where('people.data.0.startsOn', '2025-03-15')
            ->where('people.data.0.endsOn', '2025-09-15')
            ->where('people.data.1.name', 'Beto Ruiz')
            ->where('people.data.1.label', 'Periodo 2026')
            ->where('people.data.1.endsOn', '2026-09-15'));

    $this->actingAs($this->hr)->get(route('tenant.vacations.index', [$this->tenant, 'view' => 'prescribable']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('view', 'prescribable')
            ->where('people.total', 1)
            ->where('people.data.0.name', 'Ana Solís')
            ->where('people.data.0.endsOn', '2026-09-15'));
});

it('falls back to who is out for a view it does not know', function (): void {
    $this->actingAs($this->hr)->get(route('tenant.vacations.index', [$this->tenant, 'view' => 'nope']))
        ->assertInertia(fn (Assert $page) => $page->where('view', 'out'));
});

it('turns the pages of the people', function (): void {
    foreach (range(1, 12) as $number) {
        leaveSummaryRequest(leaveSummaryPerson("Persona {$number}"), 'approved', '2026-11-04', '2026-11-04');
    }

    $this->actingAs($this->hr)->get(route('tenant.vacations.index', [$this->tenant, 'page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->where('people.current_page', 2)->where('people.total', 12)->has('people.data', 2));
});

it('is for whoever may view vacations and for nobody else', function (TenantRole $role, bool $allowed): void {
    $response = $this->actingAs(memberOf($this->tenant, $role))->get(route('tenant.vacations.index', $this->tenant));

    $allowed ? $response->assertOk() : $response->assertForbidden();
})->with([
    'owner' => [TenantRole::Owner, true],
    'hr' => [TenantRole::Hr, true],
    'viewer' => [TenantRole::Viewer, true],
    'committee' => [TenantRole::Committee, false],
    'complaints' => [TenantRole::Complaints, false],
]);

it('shows nothing of another company, by URL it is a 404', function (): void {
    $other = Tenant::factory()->create(['slug' => 'otra']);
    LeaveSetting::factory()->create(['tenant_id' => $other->id]);
    $stranger = Employee::factory()->create(['tenant_id' => $other->id, 'name' => 'Extraña']);
    leaveSummaryRequest($stranger, 'approved', '2026-11-04', '2026-11-04');
    leaveSummaryPeriod($stranger, '2025-03-15', 9);

    $props = $this->actingAs($this->hr)->get(route('tenant.vacations.index', $this->tenant))->assertOk()->inertiaProps();

    expect($props['summary'])->toMatchArray(['outToday' => 0, 'liabilityDays' => 0])
        ->and($props['people']['data'])->toBe([]);

    $this->get(route('tenant.vacations.index', $other))->assertNotFound();
});

it('asks the same queries whatever the number of people, requests and periods', function (int $more): void {
    foreach (range(1, $more) as $number) {
        $person = leaveSummaryPerson("Otra {$number}");
        leaveSummaryRequest($person, 'approved', '2026-11-04', '2026-11-04');
        leaveSummaryRequest($person, 'escalated', '2026-12-01', '2026-12-02');
        leaveSummaryPeriod($person, '2025-03-15', 3);
    }
    $this->actingAs($this->hr);

    $this->expectsDatabaseQueryCount(LEAVE_SUMMARY_QUERIES);
    $this->get(route('tenant.vacations.index', $this->tenant))->assertOk();
})->with(['a few' => [1], 'many more' => [30]]);
