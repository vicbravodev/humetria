<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Leave\Http\Controllers\PolicyController;
use App\Modules\Leave\Models\LeavePolicy;
use App\Modules\Leave\Models\LeavePolicyAssignment;
use App\Modules\Leave\Models\LeavePolicyRow;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Leave\Support\PolicyPages;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;

mutates(PolicyController::class, PolicyPages::class);

beforeEach(function (): void {
    workIn($this->tenant = Tenant::factory()->create(['slug' => 'acme']));
    LeaveSetting::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function leavePoliciesForm(array $overrides = []): array
{
    return [
        'name' => 'Corporativo',
        'premium_percent' => 30,
        'work_week' => 'mon_fri',
        'allows_advance' => '1',
        'extra_expires_months' => 12,
        'extra_carryover_cap_days' => 5,
        'rows' => [
            ['kind' => 'tier', 'year_from' => 1, 'year_to' => 5, 'days' => 20],
            ['kind' => 'fixed', 'label' => 'Cumpleaños', 'days' => 1],
        ],
        ...$overrides,
    ];
}

function leavePoliciesMake(string $name, int $firstYearDays = 22): LeavePolicy
{
    $policy = LeavePolicy::factory()->create(['tenant_id' => test()->tenant->id, 'name' => $name]);
    LeavePolicyRow::factory()->create(['tenant_id' => $policy->tenant_id, 'leave_policy_id' => $policy->id, 'year_from' => 1, 'year_to' => 5, 'days' => $firstYearDays]);

    return $policy;
}

it('lives under the vacations of the company', function (): void {
    expect(route('tenant.vacations.policies.index', $this->tenant, absolute: false))->toBe('/admin/acme/vacations/policies');
});

it('shows the law first, without actions, then each policy with what it gives the first year', function (): void {
    $corporate = leavePoliciesMake('Corporativo', 24);
    LeavePolicyRow::factory()->fixed('Cumpleaños', 2)->create(['tenant_id' => $this->tenant->id, 'leave_policy_id' => $corporate->id]);
    LeavePolicyAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'leave_policy_id' => $corporate->id]);
    leavePoliciesMake('Antigua')->update(['archived_at' => now()]);
    // A policy of another company never shows.
    LeavePolicy::factory()->create(['name' => 'Ajena']);

    $props = $this->actingAs($this->hr)->get(route('tenant.vacations.policies.index', $this->tenant))->assertOk()->inertiaProps();

    expect($props['law'])->toBe(['firstYearDays' => 12, 'premiumPercent' => 25])
        ->and($props['canManage'])->toBeTrue()
        ->and(array_column($props['policies']['data'], 'name'))->toBe(['Corporativo', 'Antigua'])
        ->and($props['policies']['data'][0])->toMatchArray(['premiumPercent' => 25, 'firstYearDays' => 24, 'fixedDays' => 2, 'assigned' => 1, 'archived' => false])
        ->and($props['policies']['data'][1])->toMatchArray(['archived' => true]);
});

it('shows the policies to a viewer, who cannot write', function (): void {
    $viewer = memberOf($this->tenant, TenantRole::Viewer);
    $policy = leavePoliciesMake('Corporativo');

    $props = $this->actingAs($viewer)->get(route('tenant.vacations.policies.index', $this->tenant))->assertOk()->inertiaProps();
    expect($props['canManage'])->toBeFalse();

    $this->actingAs($viewer)->get(route('tenant.vacations.policies.edit', [$this->tenant, $policy]))->assertOk();
    $this->actingAs($viewer)->get(route('tenant.vacations.policies.create', $this->tenant))->assertForbidden();
    $this->actingAs($viewer)->post(route('tenant.vacations.policies.store', $this->tenant), leavePoliciesForm())->assertForbidden();
    $this->actingAs($viewer)->put(route('tenant.vacations.policies.update', [$this->tenant, $policy]), leavePoliciesForm())->assertForbidden();
    $this->actingAs($viewer)->post(route('tenant.vacations.policies.archive', [$this->tenant, $policy]))->assertForbidden();

    expect(LeavePolicy::query()->count())->toBe(1)->and($policy->refresh()->archived_at)->toBeNull();
});

it('is closed to whoever cannot view vacations', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Committee))->get(route('tenant.vacations.policies.index', $this->tenant))->assertForbidden();
});

it('gives the form the law floor of each year to show beside every row', function (): void {
    $props = $this->actingAs($this->hr)->get(route('tenant.vacations.policies.create', $this->tenant))->assertOk()->inertiaProps();

    expect($props['policy'])->toBeNull()
        ->and($props['floors'])->toHaveCount(60)
        ->and(array_slice($props['floors'], 0, 7))->toBe([12, 14, 16, 18, 20, 22, 22])
        ->and($props['floors'][59])->toBe(42);
});

it('creates a policy with its rows and audits it', function (): void {
    $this->actingAs($this->hr)->post(route('tenant.vacations.policies.store', $this->tenant), leavePoliciesForm())
        ->assertRedirect(route('tenant.vacations.policies.index', $this->tenant));

    $policy = LeavePolicy::query()->with('rows')->sole();

    expect($policy->only(['name', 'premium_percent', 'allows_advance', 'extra_expires_months', 'extra_carryover_cap_days']))
        ->toBe(['name' => 'Corporativo', 'premium_percent' => 30, 'allows_advance' => true, 'extra_expires_months' => 12, 'extra_carryover_cap_days' => 5])
        ->and($policy->rows)->toHaveCount(2)
        ->and(AuditEntry::query()->where('event', 'leave.policy_saved')->count())->toBe(1);
});

it('sends a row under the law back with the error in its own field, in Spanish', function (): void {
    $this->actingAs($this->hr)->post(route('tenant.vacations.policies.store', $this->tenant), leavePoliciesForm([
        'rows' => [['kind' => 'tier', 'year_from' => 1, 'year_to' => 5, 'days' => 19]],
    ]))->assertSessionHasErrors(['rows.0.days' => 'No puede ser menor a los 20 días que marca la ley.']);

    expect(LeavePolicy::query()->count())->toBe(0);
});

it('refuses a premium under 25 percent', function (): void {
    $this->actingAs($this->hr)->post(route('tenant.vacations.policies.store', $this->tenant), leavePoliciesForm(['premium_percent' => 24]))
        ->assertSessionHasErrors(['premium_percent' => 'La prima no puede ser menor a 25 %.']);

    expect(LeavePolicy::query()->count())->toBe(0);
});

it('validates the shape of the form', function (array $overrides, string $field): void {
    $this->actingAs($this->hr)->post(route('tenant.vacations.policies.store', $this->tenant), leavePoliciesForm($overrides))->assertSessionHasErrors($field);
})->with([
    'no name' => [['name' => ''], 'name'],
    'a premium over 100' => [['premium_percent' => 101], 'premium_percent'],
    'an unknown work week' => [['work_week' => 'sun'], 'work_week'],
    'a row of an unknown kind' => [['rows' => [['kind' => 'other', 'days' => 3]]], 'rows.0.kind'],
    'days that are not a number' => [['rows' => [['kind' => 'tier', 'year_from' => 1, 'year_to' => 5, 'days' => 'x']]], 'rows.0.days'],
]);

it('changes a policy and its rows', function (): void {
    $policy = leavePoliciesMake('Corporativo');

    $this->actingAs($this->hr)->put(route('tenant.vacations.policies.update', [$this->tenant, $policy]), leavePoliciesForm(['name' => 'Corporativo 2']))
        ->assertRedirect(route('tenant.vacations.policies.index', $this->tenant));

    expect($policy->refresh()->name)->toBe('Corporativo 2')->and($policy->rows()->count())->toBe(2);
});

it('opens a policy with its rows to edit it', function (): void {
    $policy = leavePoliciesMake('Corporativo', 21);

    $props = $this->actingAs($this->hr)->get(route('tenant.vacations.policies.edit', [$this->tenant, $policy]))->assertOk()->inertiaProps();

    expect($props['policy'])->toMatchArray(['id' => $policy->id, 'name' => 'Corporativo', 'premiumPercent' => 25, 'workWeek' => 'mon_fri'])
        ->and($props['policy']['rows'])->toBe([['kind' => 'tier', 'label' => null, 'yearFrom' => 1, 'yearTo' => 5, 'days' => 21]]);
});

it('archives a policy that nobody has', function (): void {
    $policy = leavePoliciesMake('Corporativo');

    $this->actingAs($this->hr)->post(route('tenant.vacations.policies.archive', [$this->tenant, $policy]))->assertRedirect();

    expect($policy->refresh()->archived_at)->not->toBeNull();
});

it('does not archive a policy that is assigned', function (): void {
    $policy = leavePoliciesMake('Corporativo');
    LeavePolicyAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'leave_policy_id' => $policy->id]);

    $this->actingAs($this->hr)->post(route('tenant.vacations.policies.archive', [$this->tenant, $policy]))
        ->assertSessionHasErrors(['policy_id' => 'Está asignada: cambia primero a quién le toca.']);

    expect($policy->refresh()->archived_at)->toBeNull();
});

it('never reaches a policy of another company: 404 by url', function (): void {
    $stranger = LeavePolicy::factory()->create();

    $this->actingAs($this->hr)->get(route('tenant.vacations.policies.edit', [$this->tenant, $stranger]))->assertNotFound();
    $this->actingAs($this->hr)->put(route('tenant.vacations.policies.update', [$this->tenant, $stranger]), leavePoliciesForm())->assertNotFound();
    $this->actingAs($this->hr)->post(route('tenant.vacations.policies.archive', [$this->tenant, $stranger]))->assertNotFound();

    expect($stranger->refresh()->archived_at)->toBeNull();
});

it('puts no amount of money in the page', function (): void {
    leavePoliciesMake('Corporativo');

    $json = json_encode($this->actingAs($this->hr)->get(route('tenant.vacations.policies.index', $this->tenant))->assertOk()->inertiaProps(), JSON_THROW_ON_ERROR);

    expect($json)->not->toContain('salary')->not->toContain('amount')->not->toContain('pesos');
});
