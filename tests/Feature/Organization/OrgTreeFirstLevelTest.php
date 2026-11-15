<?php

declare(strict_types=1);

use App\Modules\Organization\Data\OrgFilters;
use App\Modules\Organization\Data\OrgNode;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Support\OrgTree;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;

mutates(OrgTree::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
    $center = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta']);
    $this->sales = Area::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ventas']);
    $this->place = ['tenant_id' => $this->tenant->id, 'work_center_id' => $center->id];
    $this->dir = Employee::factory()->create([...$this->place, 'name' => 'Dir']);
    $this->other = Employee::factory()->create([...$this->place, 'name' => 'Otra raíz']);
    $this->gte = Employee::factory()->reportingTo($this->dir)->create([...$this->place, 'name' => 'Gte']);
    $this->ana = Employee::factory()->reportingTo($this->dir)->create([...$this->place, 'name' => 'Ana', 'area_id' => $this->sales->id]);
    $this->beto = Employee::factory()->reportingTo($this->other)->create([...$this->place, 'name' => 'Beto']);
    Employee::factory()->reportingTo($this->gte)->create([...$this->place, 'name' => 'Nieta']);
});

/**
 * @param  array<string, list<OrgNode>>  $groups
 * @return array<string, list<string>>
 */
function firstLevelNames(array $groups): array
{
    return array_map(fn (array $nodes): array => array_map(fn (OrgNode $node): string => $node->name, $nodes), $groups);
}

it('gives the first level of several people at once, each by name with the real size of their teams', function (): void {
    $groups = resolve(OrgTree::class)->firstLevel([$this->dir->id, $this->other->id], new OrgFilters);

    expect(firstLevelNames($groups))->toBe([$this->dir->id => ['Ana', 'Gte'], $this->other->id => ['Beto']])
        ->and($groups[$this->dir->id][1]->directReports)->toBe(1)
        ->and($groups[$this->dir->id][1]->totalReports)->toBe(1);
});

it('leaves out of the answer whoever leads nobody, and ignores what is not an id', function (): void {
    $groups = resolve(OrgTree::class)->firstLevel([$this->beto->id, 'ana', $this->dir->id], new OrgFilters);

    expect(array_keys($groups))->toBe([$this->dir->id])
        ->and(resolve(OrgTree::class)->firstLevel(['ana'], new OrgFilters))->toBe([])
        ->and(resolve(OrgTree::class)->firstLevel([], new OrgFilters))->toBe([]);
});

it('gives only a page of each team: the rest is asked for with the reports of the person', function (): void {
    Employee::factory()->count(52)->reportingTo($this->other)->create($this->place);

    $groups = resolve(OrgTree::class)->firstLevel([$this->dir->id, $this->other->id], new OrgFilters, perPage: 50);

    expect($groups[$this->other->id])->toHaveCount(50)->and($groups[$this->dir->id])->toHaveCount(2);
});

it('narrows by the filters, as the rest of the tree does', function (): void {
    $groups = resolve(OrgTree::class)->firstLevel([$this->dir->id], new OrgFilters(areaId: $this->sales->id));

    expect(firstLevelNames($groups))->toBe([$this->dir->id => ['Ana']]);
});

it('does not give the people of another company, even asked by id', function (): void {
    $theirBoss = Employee::factory()->create(['name' => 'Ajena']);
    Employee::factory()->reportingTo($theirBoss)->create(['name' => 'Ajeno']);

    expect(resolve(OrgTree::class)->firstLevel([$theirBoss->id], new OrgFilters))->toBe([]);
});
