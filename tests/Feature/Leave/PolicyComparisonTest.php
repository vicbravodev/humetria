<?php

declare(strict_types=1);

use App\Modules\Leave\Http\Controllers\PolicyController;
use App\Modules\Leave\Models\LeavePolicy;
use App\Modules\Leave\Models\LeavePolicyAssignment;
use App\Modules\Leave\Models\LeavePolicyRow;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Leave\Support\PolicyPages;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;

mutates(PolicyController::class, PolicyPages::class);

beforeEach(function (): void {
    workIn($this->tenant = Tenant::factory()->create(['slug' => 'acme']));
    $this->travelTo('2026-11-11 18:00:00');
    LeaveSetting::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
});

function comparisonPolicy(string $name, int $first, ?int $second = null): LeavePolicy
{
    $policy = LeavePolicy::factory()->create(['tenant_id' => test()->tenant->id, 'name' => $name]);
    LeavePolicyRow::factory()->create(['tenant_id' => $policy->tenant_id, 'leave_policy_id' => $policy->id, 'year_from' => 1, 'year_to' => 1, 'days' => $first]);

    if ($second !== null) {
        LeavePolicyRow::factory()->create(['tenant_id' => $policy->tenant_id, 'leave_policy_id' => $policy->id, 'year_from' => 2, 'year_to' => 5, 'days' => $second]);
    }

    return $policy;
}

/** @return array<string, mixed> */
function comparisonProps(): array
{
    return test()->actingAs(test()->hr)->get(route('tenant.vacations.policies.index', test()->tenant))->assertOk()->inertiaProps();
}

it('gives the days of the law by year of service, from the first to the sixth', function (): void {
    $props = comparisonProps();

    expect($props['lawByYear'])->toBe([12, 14, 16, 18, 20, 22]);
});

it('gives the days of each policy by year, the law where no tier reaches and the fixed days on top', function (): void {
    $policy = comparisonPolicy('Personal de confianza', 14, 21);
    LeavePolicyRow::factory()->fixed('Cumpleaños', 1)->create(['tenant_id' => $this->tenant->id, 'leave_policy_id' => $policy->id]);

    $card = comparisonProps()['policies']['data'][0];

    // Year 1: tier of 14 (+1 fixed). Years 2 to 5: tier of 21 (+1 fixed). Year 6: no tier, the law (22) plus the fixed day.
    expect($card['byYear'])->toBe([15, 22, 22, 22, 22, 23]);
});

it('counts how many people each policy covers by the most specific assignment, and how many follow the law', function (): void {
    $trusted = comparisonPolicy('Personal de confianza', 14);
    $union = comparisonPolicy('Contrato colectivo', 12);
    $company = comparisonPolicy('Toda la empresa', 13);
    LeavePolicyAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'leave_policy_id' => $company->id]);
    LeavePolicyAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'leave_policy_id' => $union->id, 'scope' => 'staff_group', 'staff_group' => 'unionized']);
    LeavePolicyAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'leave_policy_id' => $trusted->id, 'scope' => 'staff_group', 'staff_group' => 'trusted']);
    foreach (['unionized', 'unionized', 'trusted', null, null, null] as $group) {
        Employee::factory()->create(['tenant_id' => $this->tenant->id, 'staff_group' => $group]);
    }
    Employee::factory()->deactivated()->create(['tenant_id' => $this->tenant->id, 'staff_group' => 'unionized']);

    $cards = array_column(comparisonProps()['policies']['data'], null, 'name');

    expect($cards['Contrato colectivo']['people'])->toBe(2)
        ->and($cards['Personal de confianza']['people'])->toBe(1)
        ->and($cards['Toda la empresa']['people'])->toBe(3)
        ->and(comparisonProps()['lawPeople'])->toBe(0);
});

it('says how many follow the law when nothing is assigned, and ignores another company', function (): void {
    Employee::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);
    $other = Tenant::factory()->create();
    Employee::factory()->create(['tenant_id' => $other->id]);

    expect(comparisonProps()['lawPeople'])->toBe(2);
});
