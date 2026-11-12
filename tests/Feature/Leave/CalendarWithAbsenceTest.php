<?php

declare(strict_types=1);

use App\Modules\Leave\Http\Controllers\CalendarController;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Leave\Support\CalendarPages;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;

mutates(CalendarController::class, CalendarPages::class);

beforeEach(function (): void {
    workIn($this->tenant = Tenant::factory()->create(['slug' => 'acme']));
    // Wednesday 11 November 2026, noon in Mexico City.
    $this->travelTo('2026-11-11 18:00:00');
    LeaveSetting::factory()->create(['tenant_id' => $this->tenant->id, 'tracking_since' => '2025-01-01']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->alonso = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Alonso Villanueva']);
    $this->sara = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Sara Gutiérrez']);
    $this->ivan = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Iván Castañeda']);
    $this->zoe = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Zoe Sin Ausencia']);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function withAbsenceRequest(Employee $person, string $state, string $startsOn, string $endsOn, array $attributes = []): LeaveRequest
{
    $factory = LeaveRequest::factory();
    $factory = $state === 'approved' ? $factory->approved() : $factory;

    return $factory->create(['tenant_id' => $person->tenant_id, 'employee_id' => $person->id, 'starts_on' => $startsOn, 'ends_on' => $endsOn, ...$attributes]);
}

/**
 * @param  array<string, mixed>  $query
 * @return array<string, mixed>
 */
function withAbsenceProps(array $query = [], ?TenantRole $role = null): array
{
    $user = $role === null ? test()->hr : memberOf(test()->tenant, $role);

    return test()->actingAs($user)->get(route('tenant.vacations.calendar', [test()->tenant, ...$query]))->assertOk()->inertiaProps();
}

/**
 * @param  array<string, mixed>  $props
 * @return list<string>
 */
function withAbsenceNames(array $props): array
{
    return array_column($props['people']['data'], 'name');
}

it('opens on the people who have an absence in the month, and says it', function (): void {
    withAbsenceRequest($this->alonso, 'approved', '2026-11-17', '2026-11-18');
    withAbsenceRequest($this->sara, 'approved', '2026-11-19', '2026-11-20');

    $props = withAbsenceProps();

    expect(withAbsenceNames($props))->toBe(['Alonso Villanueva', 'Sara Gutiérrez'])
        ->and($props['showAll'])->toBeFalse()
        ->and($props['counts'])->toBe(['absent' => 2, 'everyone' => 4]);
});

it('lists everybody when asked, absent or not', function (): void {
    withAbsenceRequest($this->alonso, 'approved', '2026-11-17', '2026-11-18');

    $props = withAbsenceProps(['with_absence' => '0']);

    expect(withAbsenceNames($props))->toBe(['Alonso Villanueva', 'Iván Castañeda', 'Sara Gutiérrez', 'Zoe Sin Ausencia'])
        ->and($props['showAll'])->toBeTrue()
        ->and($props['counts'])->toBe(['absent' => 1, 'everyone' => 4]);
});

it('counts as absent the ones whose answer is still pending only for who manages vacations', function (): void {
    withAbsenceRequest($this->ivan, 'requested', '2026-11-18', '2026-11-20');

    expect(withAbsenceNames(withAbsenceProps()))->toBe(['Iván Castañeda'])
        ->and(withAbsenceNames(withAbsenceProps([], TenantRole::Viewer)))->toBe([])
        ->and(withAbsenceProps([], TenantRole::Viewer)['counts']['absent'])->toBe(0);
});

it('leaves out an absence of another month, one that was cancelled, and a person of another company', function (): void {
    withAbsenceRequest($this->alonso, 'approved', '2026-10-05', '2026-10-06');
    withAbsenceRequest($this->sara, 'approved', '2026-12-01', '2026-12-02');
    LeaveRequest::factory()->approved()->cancelled()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ivan->id, 'starts_on' => '2026-11-17', 'ends_on' => '2026-11-18']);
    $other = Tenant::factory()->create(['slug' => 'otra']);
    $stranger = Employee::factory()->create(['tenant_id' => $other->id, 'name' => 'Extraña']);
    LeaveRequest::factory()->approved()->create(['tenant_id' => $other->id, 'employee_id' => $stranger->id, 'starts_on' => '2026-11-17', 'ends_on' => '2026-11-18']);

    $props = withAbsenceProps();

    expect(withAbsenceNames($props))->toBe([])
        ->and($props['counts'])->toBe(['absent' => 0, 'everyone' => 4]);
});

it('narrows by area before keeping the absent ones, and counts what the filters leave', function (): void {
    $sales = Area::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ventas']);
    $this->alonso->update(['area_id' => $sales->id]);
    withAbsenceRequest($this->alonso, 'approved', '2026-11-17', '2026-11-18');
    withAbsenceRequest($this->sara, 'approved', '2026-11-17', '2026-11-18');

    $props = withAbsenceProps(['area' => $sales->id]);

    expect(withAbsenceNames($props))->toBe(['Alonso Villanueva'])
        ->and($props['counts'])->toBe(['absent' => 1, 'everyone' => 1]);
});

it('pages the absent ones on the server too', function (): void {
    foreach (range(1, 27) as $number) {
        $person = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => sprintf('Persona %02d', $number)]);
        withAbsenceRequest($person, 'approved', '2026-11-17', '2026-11-18');
    }

    $props = withAbsenceProps();

    expect($props['people']['total'])->toBe(27)
        ->and($props['people']['data'])->toHaveCount(25);
});
