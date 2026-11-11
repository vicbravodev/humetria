<?php

declare(strict_types=1);

use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Organization\Data\OrgFilters;
use App\Modules\Organization\Data\OrgNode;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Support\OrgTree;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;

beforeEach(function (): void {
    // Tuesday 10 November 2026, noon in Mexico City.
    $this->travelTo('2026-11-10 18:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->place = [
        'tenant_id' => $this->tenant->id,
        'work_center_id' => WorkCenter::factory()->for($this->tenant)->create()->id,
        'area_id' => Area::factory()->create(['tenant_id' => $this->tenant->id])->id,
    ];
    $this->dir = Employee::factory()->create([...$this->place, 'name' => 'Dir']);
    $this->ana = Employee::factory()->reportingTo($this->dir)->create([...$this->place, 'name' => 'Ana']);
    $this->beto = Employee::factory()->reportingTo($this->dir)->create([...$this->place, 'name' => 'Beto']);
    $this->tree = resolve(OrgTree::class);
});

/**
 * Approved vacations of a person, from one day to another.
 */
function orgAway(Employee $person, string $from, string $to): LeaveRequest
{
    return LeaveRequest::factory()->approved()->create(['tenant_id' => $person->tenant_id, 'employee_id' => $person->id, 'starts_on' => $from, 'ends_on' => $to, 'working_days' => 1]);
}

/**
 * @param  iterable<OrgNode>  $nodes
 * @return array<string, bool> whether each person is away today, by name
 */
function awayByName(iterable $nodes): array
{
    return array_column(array_map(fn (OrgNode $node): array => $node->toArray(), [...$nodes]), 'absent_today', 'name');
}

it('marks who is away today with approved vacations, and not their sibling', function (): void {
    orgAway($this->ana, '2026-11-09', '2026-11-13');

    expect(awayByName($this->tree->children($this->dir->id, new OrgFilters)))->toBe(['Ana' => true, 'Beto' => false]);
});

it('marks the roots and the chain above a person too', function (): void {
    orgAway($this->dir, '2026-11-10', '2026-11-10');

    expect(awayByName($this->tree->roots(new OrgFilters)))->toBe(['Dir' => true])
        ->and(awayByName($this->tree->chain($this->ana->id)))->toBe(['Dir' => true, 'Ana' => false]);
});

it('counts today as people in Mexico live it, and only what was approved', function (): void {
    orgAway($this->ana, '2026-11-11', '2026-11-11');
    LeaveRequest::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->beto->id, 'starts_on' => '2026-11-09', 'ends_on' => '2026-11-13']);

    // 05:30 UTC of Wednesday 11 is still Tuesday 10 in Mexico City.
    $this->travelTo('2026-11-11 05:30:00');
    expect(awayByName($this->tree->children($this->dir->id, new OrgFilters)))->toBe(['Ana' => false, 'Beto' => false]);

    $this->travelTo('2026-11-11 06:30:00');
    expect(awayByName($this->tree->children($this->dir->id, new OrgFilters)))->toBe(['Ana' => true, 'Beto' => false]);
});

it('is not away unless told so', function (): void {
    expect((new OrgNode('id', 'Ana', '', '', '', true, 0, 0, null, true, false))->toArray()['absent_today'])->toBeFalse();
});

it('asks for the absences of a batch of nodes once, however many they are', function (): void {
    Employee::factory()->count(18)->reportingTo($this->dir)->create($this->place);

    // The count, the page and the work centers, and one for who is away.
    $this->expectsDatabaseQueryCount(4);

    expect($this->tree->children($this->dir->id, new OrgFilters)->items())->toHaveCount(20);
});

it('tells the org chart only that a person is away: never the dates nor what kind of absence', function (): void {
    $maternity = LeaveType::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Maternidad']);
    LeaveRequest::factory()->ofType($maternity)->approved()->create(['employee_id' => $this->ana->id, 'starts_on' => '2026-11-02', 'ends_on' => '2026-11-27', 'working_days' => 19]);
    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer));

    $props = $this->get(route('tenant.org-chart.index', [$this->tenant, 'focus' => $this->ana->id]))->assertOk()->inertiaProps();
    $reports = $this->getJson(route('tenant.org-chart.reports', [$this->tenant, $this->dir->id]))->assertOk()->json('data');
    $told = json_encode([$props, $reports], JSON_THROW_ON_ERROR);

    expect(array_column($reports, 'absent_today', 'name'))->toBe(['Ana' => true, 'Beto' => false])
        ->and(array_column($props['chain'], 'absent_today', 'name'))->toBe(['Dir' => false, 'Ana' => true])
        ->and(array_keys($reports[0]))->toBe(['id', 'name', 'job_title', 'area', 'work_center', 'active', 'direct_reports', 'total_reports', 'flag', 'matches', 'manager_inactive', 'absent_today'])
        ->and($told)->not->toContain('Maternidad', '2026-11-02', '2026-11-27', 'vacation', '"leave"');
});
