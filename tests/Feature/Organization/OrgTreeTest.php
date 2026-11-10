<?php

declare(strict_types=1);

use App\Modules\Organization\Data\OrgFilters;
use App\Modules\Organization\Data\OrgNode;
use App\Modules\Organization\Enums\SpanFlag;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Support\OrgTree;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Facades\DB;

mutates(OrgTree::class, OrgNode::class, OrgFilters::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta']);
    $this->office = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Oficina']);
    $this->production = Area::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Producción']);
    $this->sales = Area::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ventas']);
    $place = ['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'area_id' => $this->production->id];

    // Dir is at the top; A leads Ana (the only one in Ventas, at the office) and Beto; B leads 17 people.
    $this->dir = Employee::factory()->create([...$place, 'name' => 'Dir', 'job_title' => 'Directora']);
    $this->a = Employee::factory()->reportingTo($this->dir)->create([...$place, 'name' => 'Gte A']);
    $this->b = Employee::factory()->reportingTo($this->dir)->create([...$place, 'name' => 'Gte B']);
    $this->ana = Employee::factory()->reportingTo($this->a)->create([...$place, 'name' => 'Ana', 'area_id' => $this->sales->id, 'work_center_id' => $this->office->id]);
    $this->beto = Employee::factory()->reportingTo($this->a)->create([...$place, 'name' => 'Beto']);
    Employee::factory()->count(17)->reportingTo($this->b)->create($place);
    $this->place = $place;
    $this->tree = resolve(OrgTree::class);
});

/** @param  iterable<OrgNode>  $nodes */
function nodeNames(iterable $nodes): array
{
    return array_map(fn (OrgNode $node): string => $node->name, [...$nodes]);
}

it('lists whoever has no manager, with the real size of their team', function (): void {
    $roots = $this->tree->roots(new OrgFilters);

    expect($roots->total())->toBe(1)
        ->and($roots->items()[0]->toArray())->toBe([
            'id' => $this->dir->id,
            'name' => 'Dir',
            'job_title' => 'Directora',
            'area' => 'Producción',
            'work_center' => 'Planta',
            'active' => true,
            'direct_reports' => 2,
            'total_reports' => 21,
            'flag' => ['value' => 'few', 'label' => 'Revisar: pocos reportes'],
            'matches' => true,
            'manager_inactive' => false,
            'absent_today' => false,
        ]);
});

it('lists the active direct reports of a person by name, marking the extremes', function (): void {
    Employee::factory()->reportingTo($this->dir)->deactivated()->create([...$this->place, 'name' => 'Aaron']);
    Employee::factory()->reportingTo($this->a)->deactivated()->create($this->place);

    $children = $this->tree->children($this->dir->id, new OrgFilters);

    expect(nodeNames($children))->toBe(['Gte A', 'Gte B'])
        ->and($children->total())->toBe(2)
        ->and([$children->items()[0]->directReports, $children->items()[0]->totalReports, $children->items()[0]->flag])->toBe([2, 2, SpanFlag::Few])
        ->and([$children->items()[1]->directReports, $children->items()[1]->flag])->toBe([17, SpanFlag::Many])
        ->and($this->tree->children($this->ana->id, new OrgFilters)->total())->toBe(0)
        ->and($this->tree->roots(new OrgFilters)->items()[0]->totalReports)->toBe(21);
});

it('names a person without a name by their company ID, and leaves a missing area empty', function (): void {
    Employee::factory()->reportingTo($this->ana)->create([...$this->place, 'name' => null, 'employee_number' => 'S-77', 'job_title' => null, 'area_id' => null, 'work_center_id' => null]);

    $node = $this->tree->children($this->ana->id, new OrgFilters)->items()[0];

    expect([$node->name, $node->jobTitle, $node->area, $node->workCenter, $node->flag])->toBe(['S-77', '', '', '', null]);
});

it('keeps in the roots an active person whose manager left, and says so', function (): void {
    $this->a->update(['deactivated_at' => now()]);

    $roots = $this->tree->roots(new OrgFilters);

    // Whoever leads people comes first, then by name.
    expect(nodeNames($roots))->toBe(['Dir', 'Ana', 'Beto'])
        ->and(array_map(fn (OrgNode $node): bool => $node->managerInactive, $roots->items()))->toBe([false, true, true])
        // A no longer counts, nor do the people only reachable through them.
        ->and([$roots->items()[0]->directReports, $roots->items()[0]->totalReports])->toBe([1, 18]);
});

it('shows with a filter only the branches where someone matches, without changing the counts', function (OrgFilters $filters): void {
    $children = $this->tree->children($this->dir->id, $filters);
    $below = $this->tree->children($this->a->id, $filters);

    expect(nodeNames($children))->toBe(['Gte A'])
        ->and($children->total())->toBe(1)
        ->and([$children->items()[0]->matches, $children->items()[0]->directReports, $children->items()[0]->totalReports])->toBe([false, 2, 2])
        ->and(nodeNames($below))->toBe(['Ana'])
        ->and($below->items()[0]->matches)->toBeTrue()
        ->and(nodeNames($this->tree->roots($filters)))->toBe(['Dir'])
        ->and($this->tree->roots($filters)->items()[0]->matches)->toBeFalse();
})->with([
    'area' => fn () => new OrgFilters(areaId: test()->sales->id),
    'work center' => fn () => new OrgFilters(workCenterId: test()->office->id),
    'both' => fn () => new OrgFilters(test()->office->id, test()->sales->id),
]);

it('shows nobody when the filters match no one', function (): void {
    $filters = new OrgFilters($this->office->id, $this->production->id);

    expect($filters->isEmpty())->toBeFalse()
        ->and((new OrgFilters)->isEmpty())->toBeTrue()
        ->and((new OrgFilters(areaId: $this->sales->id))->isEmpty())->toBeFalse()
        ->and((new OrgFilters(workCenterId: $this->office->id))->isEmpty())->toBeFalse()
        ->and($this->tree->roots($filters)->total())->toBe(0)
        ->and($this->tree->roots($filters)->items())->toBe([]);
});

it('tells the chain from the top down to a person, keeping whoever left', function (): void {
    $this->a->update(['deactivated_at' => now()]);

    $chain = $this->tree->chain($this->ana->id);

    expect(nodeNames($chain))->toBe(['Dir', 'Gte A', 'Ana'])
        ->and(array_map(fn (OrgNode $node): bool => $node->active, $chain))->toBe([true, false, true])
        ->and($chain[2]->managerInactive)->toBeTrue()
        ->and($chain[1]->directReports)->toBe(2)
        ->and(nodeNames($this->tree->chain($this->dir->id)))->toBe(['Dir']);
});

it('answers nothing about a person of another company', function (): void {
    $other = Employee::factory()->create();
    Employee::factory()->reportingTo($other)->create();

    expect($this->tree->children($other->id, new OrgFilters)->total())->toBe(0)
        ->and($this->tree->children($other->id, new OrgFilters)->items())->toBe([])
        ->and($this->tree->chain($other->id))->toBe([])
        ->and($this->tree->chain('not-a-uuid'))->toBe([])
        ->and($this->tree->children('not-a-uuid', new OrgFilters)->total())->toBe(0);
});

it('pages the direct reports of a manager', function (): void {
    foreach (range(1, 43) as $number) {
        Employee::factory()->reportingTo($this->b)->create([...$this->place, 'name' => sprintf('Persona %02d', $number)]);
    }

    $first = $this->tree->children($this->b->id, new OrgFilters);
    $filtered = $this->tree->children($this->b->id, new OrgFilters(areaId: $this->production->id), 25);

    expect($first->total())->toBe(60)
        ->and($first->perPage())->toBe(50)
        ->and($first->items())->toHaveCount(50)
        ->and($first->hasMorePages())->toBeTrue()
        ->and($first->getPageName())->toBe('page')
        ->and($filtered->total())->toBe(60)
        ->and($filtered->items())->toHaveCount(25);
});

it('reads the second page where the first one ended', function (OrgFilters $filters): void {
    $everyone = array_column($this->tree->children($this->b->id, $filters, 17)->items(), 'id');
    request()->merge(['page' => 2]);

    $second = $this->tree->children($this->b->id, $filters, 10);

    expect($second->currentPage())->toBe(2)
        ->and($second->total())->toBe(17)
        ->and($second->items())->toHaveCount(7)
        ->and(array_column($second->items(), 'id'))->toBe(array_slice($everyone, 10));
})->with([
    'unfiltered' => fn () => new OrgFilters,
    'filtered' => fn () => new OrgFilters(areaId: test()->production->id),
]);

it('reads a page of the tree with the same queries whatever the size of the team', function (string $manager): void {
    // The count, the page and the work centers, and who of the page is away today.
    $this->expectsDatabaseQueryCount(4);

    $this->tree->children($this->{$manager}->id, new OrgFilters);
})->with(['two reports' => 'a', 'seventeen reports' => 'b']);

it('reads the roots and the chain with a fixed number of queries', function (): void {
    // 5 of the tree, and who is away today of each of the two batches.
    $this->expectsDatabaseQueryCount(7);

    $this->tree->roots(new OrgFilters(areaId: $this->sales->id));
    $this->tree->chain($this->ana->id);
});

it('ends on a cycle already in the data', function (): void {
    // A restore with the trigger disabled: A reports to Ana, who reports to A.
    DB::statement('ALTER TABLE employees DISABLE TRIGGER employees_manager_acyclic');
    DB::table('employees')->where('id', $this->a->id)->update(['manager_id' => $this->ana->id]);
    DB::statement('ALTER TABLE employees ENABLE TRIGGER employees_manager_acyclic');

    $children = $this->tree->children($this->a->id, new OrgFilters);

    expect(nodeNames($this->tree->roots(new OrgFilters)))->toBe(['Dir'])
        ->and(nodeNames($children))->toBe(['Ana', 'Beto'])
        // Ana leads A, who leads Beto and (again) Ana: nobody is counted twice.
        ->and([$children->items()[0]->directReports, $children->items()[0]->totalReports])->toBe([1, 2])
        ->and(nodeNames($this->tree->chain($this->a->id)))->toBe(['Ana', 'Gte A']);
});
