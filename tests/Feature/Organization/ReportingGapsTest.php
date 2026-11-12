<?php

declare(strict_types=1);

use App\Modules\Organization\Enums\ReportingIssue;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Support\ReportingGaps;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;

mutates(ReportingGaps::class, ReportingIssue::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
    $place = ['tenant_id' => $this->tenant->id];

    // Two people at the top with reports, three on their own, and one whose manager left.
    $this->dir = Employee::factory()->create([...$place, 'name' => 'Dir']);
    $this->ana = Employee::factory()->reportingTo($this->dir)->create([...$place, 'name' => 'Ana']);
    $this->other = Employee::factory()->create([...$place, 'name' => 'Otra raíz']);
    Employee::factory()->reportingTo($this->other)->create([...$place, 'name' => 'Beto']);
    foreach (['Suelta C', 'Suelta A', 'Suelta B'] as $name) {
        Employee::factory()->create([...$place, 'name' => $name]);
    }
    $this->gone = Employee::factory()->reportingTo($this->dir)->deactivated()->create([...$place, 'name' => 'Se fue']);
    Employee::factory()->reportingTo($this->gone)->create([...$place, 'name' => 'Huérfana']);
    $this->place = $place;
    $this->gaps = resolve(ReportingGaps::class);
});

/** @return list<string|null> */
function gapNames(ReportingIssue $issue, string $search = '', ?string $center = null): array
{
    return array_map(fn (Employee $employee): ?string => $employee->name, test()->gaps->page($issue, search: $search, workCenterId: $center)->items());
}

it('names each view of the list as the screen shows it', function (): void {
    expect(array_map(fn (ReportingIssue $issue): array => [$issue->value, $issue->label()], ReportingIssue::views()))->toBe([
        ['all', 'Todas'],
        ['no_manager', 'Sin jefe'],
        ['many_reports', 'Más de 15 directos'],
        ['inactive_manager', 'Jefe dado de baja'],
    ])
        ->and(ReportingIssue::WithoutManager->label())->toBe('Sin jefe');
});

it('takes anyone as something to fix, except one person at the top and everybody', function (): void {
    expect(array_map(fn (int $count): bool => ReportingIssue::All->pending($count), [0, 5]))->toBe([false, false])
        ->and(array_map(fn (int $count): bool => ReportingIssue::NoManager->pending($count), [0, 1, 2]))->toBe([false, false, true])
        ->and(array_map(fn (int $count): bool => ReportingIssue::ManyReports->pending($count), [0, 1]))->toBe([false, true])
        ->and(array_map(fn (int $count): bool => ReportingIssue::InactiveManager->pending($count), [0, 1]))->toBe([false, true])
        ->and(array_map(fn (int $count): bool => ReportingIssue::WithoutManager->pending($count), [0, 1]))->toBe([false, true]);
});

it('counts every view of the list', function (): void {
    expect($this->gaps->counts())->toBe(['all' => 8, 'no_manager' => 5, 'many_reports' => 0, 'inactive_manager' => 1, 'without_manager' => 3]);
});

it('counts as many reports whoever leads 16 active people, not 15', function (): void {
    Employee::factory()->count(15)->reportingTo($this->ana)->create($this->place);

    expect($this->gaps->counts()['many_reports'])->toBe(0);

    Employee::factory()->reportingTo($this->ana)->create($this->place);
    Employee::factory()->count(4)->reportingTo($this->ana)->deactivated()->create($this->place);

    expect($this->gaps->counts()['many_reports'])->toBe(1)
        ->and(gapNames(ReportingIssue::ManyReports))->toBe(['Ana']);
});

it('lists the people of each view, by name, and nobody else', function (): void {
    expect(gapNames(ReportingIssue::WithoutManager))->toBe(['Suelta A', 'Suelta B', 'Suelta C'])
        ->and(gapNames(ReportingIssue::NoManager))->toBe(['Dir', 'Otra raíz', 'Suelta A', 'Suelta B', 'Suelta C'])
        ->and(gapNames(ReportingIssue::InactiveManager))->toBe(['Huérfana']);
});

it('lists everybody with whoever has nobody above first, each group by name', function (): void {
    expect(gapNames(ReportingIssue::All))->toBe(['Dir', 'Otra raíz', 'Suelta A', 'Suelta B', 'Suelta C', 'Ana', 'Beto', 'Huérfana']);
});

it('leaves out whoever left', function (): void {
    // Someone who left with no manager, and one who left under a manager who left, are nobody's gap.
    Employee::factory()->deactivated()->create([...$this->place, 'name' => 'Baja suelta']);
    Employee::factory()->reportingTo($this->gone)->deactivated()->create($this->place);
    // The only report of a person at the top left: that person is on their own now.
    $alone = Employee::factory()->create([...$this->place, 'name' => 'Suelta D']);
    Employee::factory()->reportingTo($alone)->deactivated()->create($this->place);

    expect($this->gaps->counts())->toBe(['all' => 9, 'no_manager' => 6, 'many_reports' => 0, 'inactive_manager' => 1, 'without_manager' => 4])
        ->and(gapNames(ReportingIssue::WithoutManager))->toBe(['Suelta A', 'Suelta B', 'Suelta C', 'Suelta D'])
        ->and(gapNames(ReportingIssue::InactiveManager))->toBe(['Huérfana']);
});

it('leaves out another company', function (): void {
    $stranger = Employee::factory()->create();
    Employee::factory()->reportingTo($stranger)->create();
    $left = Employee::factory()->deactivated()->create();
    Employee::factory()->reportingTo($left)->create();

    expect($this->gaps->counts())->toBe(['all' => 8, 'no_manager' => 5, 'many_reports' => 0, 'inactive_manager' => 1, 'without_manager' => 3])
        ->and(gapNames(ReportingIssue::NoManager))->toBe(['Dir', 'Otra raíz', 'Suelta A', 'Suelta B', 'Suelta C']);
});

it('counts nothing where there is nobody', function (): void {
    workIn(Tenant::factory()->create());

    expect($this->gaps->counts())->toBe(['all' => 0, 'no_manager' => 0, 'many_reports' => 0, 'inactive_manager' => 0, 'without_manager' => 0])
        ->and($this->gaps->page(ReportingIssue::All)->total())->toBe(0);
});

it('searches by name or employee number, and narrows to a work center', function (): void {
    $north = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Norte']);
    Employee::query()->where('name', 'Suelta B')->update(['work_center_id' => $north->id]);

    expect(gapNames(ReportingIssue::All, 'suelta b'))->toBe(['Suelta B'])
        ->and(gapNames(ReportingIssue::NoManager, 'OTRA'))->toBe(['Otra raíz'])
        ->and(gapNames(ReportingIssue::All, 'nadie con ese nombre'))->toBe([])
        ->and(gapNames(ReportingIssue::All, '', $north->id))->toBe(['Suelta B'])
        ->and(gapNames(ReportingIssue::All, 'suelta a', $north->id))->toBe([])
        ->and(gapNames(ReportingIssue::All, '', (string) Illuminate\Support\Str::uuid()))->toBe([]);
});

it('pages the people of a view, keeping the query in the links', function (): void {
    Employee::factory()->count(30)->create($this->place);
    request()->merge(['issue' => 'without_manager']);

    $page = $this->gaps->page(ReportingIssue::WithoutManager);

    expect($page->total())->toBe(33)
        ->and($page->perPage())->toBe(25)
        ->and($page->items())->toHaveCount(25)
        ->and($page->nextPageUrl())->toContain('issue=without_manager')
        ->and($this->gaps->page(ReportingIssue::WithoutManager, 10)->items())->toHaveCount(10);
});

it('brings the area, the manager and the team size of each person, without a query per row', function (): void {
    Employee::factory()->count(5)->reportingTo($this->gone)->create($this->place);

    // The count, the page (with the size of each team), the areas and the managers.
    $this->expectsDatabaseQueryCount(4);

    $people = $this->gaps->page(ReportingIssue::InactiveManager)->items();

    expect($people)->toHaveCount(6)
        ->and($people[0]->manager?->name)->toBe('Se fue')
        ->and($people[0]->area?->name)->toBeString()
        ->and($people[0]->getAttributeValue('direct_reports'))->toBe(0);
});

it('counts the views with one query', function (): void {
    $this->expectsDatabaseQueryCount(1);

    $this->gaps->counts();
});
