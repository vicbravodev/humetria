<?php

declare(strict_types=1);

use App\Modules\Leave\Http\Controllers\BalanceController;
use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveEntry;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Leave\Models\LeaveStatement;
use App\Modules\Leave\Models\LeaveTermination;
use App\Modules\Leave\Notifications\VacationsPortalLink;
use App\Modules\Leave\Support\BalancePages;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Notification;

mutates(BalanceController::class, BalancePages::class);

/** The queries of the list of balances, whatever the number of people on the page (the session and the panel frame included). */
const LEAVE_BALANCES_QUERIES = 31;

beforeEach(function (): void {
    workIn($this->tenant = Tenant::factory()->create(['slug' => 'acme']));
    // Noon in Mexico City.
    $this->travelTo('2026-11-04 18:00:00');
    LeaveSetting::factory()->create(['tenant_id' => $this->tenant->id, 'tracking_since' => '2025-01-01']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->ana = leaveBalancesPerson('Ana Solís', '2024-03-15');
    $this->beto = leaveBalancesPerson('Beto Ruiz', '2020-01-15');
});

function leaveBalancesPerson(string $name, ?string $hiredOn): Employee
{
    return Employee::factory()->create(['tenant_id' => test()->tenant->id, 'name' => $name, 'hired_on' => $hiredOn]);
}

/**
 * @param  array<string, mixed>  $query
 * @return array<string, mixed>
 */
function leaveBalancesProps(array $query = []): array
{
    return test()->actingAs(test()->hr)->get(route('tenant.vacations.balances.index', [test()->tenant, ...$query]))->assertOk()->inertiaProps();
}

it('lives under the vacations of the company and is hidden from a union', function (): void {
    expect(route('tenant.vacations.balances.index', $this->tenant, absolute: false))->toBe('/admin/acme/vacations/balances');

    $union = Tenant::factory()->union()->create();

    $this->actingAs(memberOf($union, TenantRole::Owner))->get(route('tenant.vacations.balances.index', $union))->assertNotFound();
});

it('lists the people with their policy, the days they can ask for, those given in advance and the next anniversary', function (): void {
    LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'years_of_service' => 1, 'anniversary_on' => '2025-03-15', 'statutory_days' => 12, 'extra_days' => 3]);
    LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'years_of_service' => 2, 'anniversary_on' => '2026-03-15', 'statutory_days' => 14]);
    LeaveEntry::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->beto->id, 'leave_entitlement_id' => null, 'kind' => 'taken', 'days' => -2]);

    LeaveRequest::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'leave_type_id' => null, 'status' => 'requested', 'working_days' => 3]);

    $props = leaveBalancesProps();
    $people = collect($props['people']['data'])->keyBy('name');

    expect($people['Ana Solís'])->toMatchArray(['available' => 29, 'waiting' => 3, 'advanced' => 0, 'policy' => 'Ley', 'nextAnniversary' => '2027-03-15', 'active' => true])
        ->and($people['Beto Ruiz'])->toMatchArray(['available' => -2, 'waiting' => 0, 'advanced' => 2, 'nextAnniversary' => '2027-01-15']);
});

it('counts each tab and lists everybody on the one that is asked for', function (): void {
    $noDate = leaveBalancesPerson('Caro Luz', null);
    $period = LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'years_of_service' => 1, 'anniversary_on' => '2025-03-15']);
    LeaveStatement::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'leave_entitlement_id' => $period->id, 'acknowledged_at' => null]);
    LeaveTermination::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->beto->id]);
    // Settled, voided or acknowledged ones do not count.
    LeaveTermination::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $noDate->id, 'settled_at' => now(), 'settled_by' => $this->hr->id]);

    $props = leaveBalancesProps(['tab' => 'all']);

    expect($props['tab'])->toBe('all')
        ->and($props['counts'])->toBe(['all' => 3, 'missing' => 1, 'statements' => 1, 'terminations' => 1])
        ->and($props['people']['data'])->toHaveCount(3);
});

it('lists whoever has no start date under its tab and takes them out once it is captured', function (): void {
    $caro = leaveBalancesPerson('Caro Luz', null);

    $props = leaveBalancesProps(['tab' => 'missing']);

    expect(array_column($props['people']['data'], 'name'))->toBe(['Caro Luz']);

    $caro->update(['hired_on' => '2023-05-01']);

    expect(leaveBalancesProps(['tab' => 'missing'])['people']['data'])->toBe([]);
});

it('lists whoever has a statement to acknowledge and whoever left with days to settle', function (): void {
    $period = LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'years_of_service' => 1, 'anniversary_on' => '2025-03-15']);
    LeaveStatement::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'leave_entitlement_id' => $period->id, 'acknowledged_at' => null]);
    LeaveTermination::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->beto->id]);

    expect(array_column(leaveBalancesProps(['tab' => 'statements'])['people']['data'], 'name'))->toBe(['Ana Solís'])
        ->and(array_column(leaveBalancesProps(['tab' => 'terminations'])['people']['data'], 'name'))->toBe(['Beto Ruiz']);
});

it('searches by name without accents or case and keeps the page of the list', function (): void {
    expect(array_column(leaveBalancesProps(['q' => 'SOLIS'])['people']['data'], 'name'))->toBe(['Ana Solís']);

    foreach (range(1, 30) as $number) {
        leaveBalancesPerson("Otra {$number}", '2022-01-01');
    }

    $people = leaveBalancesProps()['people'];

    expect($people['per_page'])->toBe(10)->and($people['total'])->toBe(32)->and($people['data'])->toHaveCount(10);
});

it('shows the balances to a viewer but sends the links only owner and hr', function (TenantRole $role, bool $allowed): void {
    Notification::fake();
    $user = memberOf($this->tenant, $role);

    $this->actingAs($user)->get(route('tenant.vacations.balances.index', $this->tenant))->assertOk();
    $this->actingAs($user)->post(route('tenant.vacations.balances.links', $this->tenant), ['employees' => [$this->ana->id]])
        ->assertStatus($allowed ? 302 : 403);

    $allowed ? Notification::assertSentOnDemandTimes(VacationsPortalLink::class, 1) : Notification::assertNothingSent();
})->with([
    'owner' => [TenantRole::Owner, true],
    'hr' => [TenantRole::Hr, true],
    'viewer' => [TenantRole::Viewer, false],
]);

it('is closed to whoever cannot view vacations', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Committee))->get(route('tenant.vacations.balances.index', $this->tenant))->assertForbidden();
});

it('sends the link only to people of this company', function (): void {
    Notification::fake();
    $other = Tenant::factory()->create();
    $stranger = Employee::factory()->create(['tenant_id' => $other->id]);

    $this->actingAs($this->hr)->post(route('tenant.vacations.balances.links', $this->tenant), ['employees' => [$stranger->id, $this->ana->id]])
        ->assertRedirect();

    Notification::assertSentOnDemandTimes(VacationsPortalLink::class, 1);
    $this->actingAs($this->hr)->post(route('tenant.vacations.balances.links', $this->tenant), ['employees' => []])->assertSessionHasErrors('employees');
});

it('shows nothing of another company', function (): void {
    $other = Tenant::factory()->create(['slug' => 'otra']);
    LeaveSetting::factory()->create(['tenant_id' => $other->id]);
    Employee::factory()->create(['tenant_id' => $other->id, 'name' => 'Extraña', 'hired_on' => null]);

    $names = array_column(leaveBalancesProps(['tab' => 'all'])['people']['data'], 'name');

    expect($names)->not->toContain('Extraña')
        ->and(leaveBalancesProps()['counts']['missing'])->toBe(0);
    $this->get(route('tenant.vacations.balances.index', $other))->assertNotFound();
});

it('asks the same queries whatever the number of people on the page', function (int $more): void {
    foreach (range(1, $more) as $number) {
        $person = leaveBalancesPerson("Otra {$number}", '2022-01-01');
        LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $person->id, 'years_of_service' => 1, 'anniversary_on' => '2023-01-01']);
    }
    $this->actingAs($this->hr);

    $this->expectsDatabaseQueryCount(LEAVE_BALANCES_QUERIES);
    $this->get(route('tenant.vacations.balances.index', $this->tenant))->assertOk();
})->with(['a few' => [3], 'a full page' => [25]]);
