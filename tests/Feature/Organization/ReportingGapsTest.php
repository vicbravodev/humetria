<?php

declare(strict_types=1);

use App\Modules\Organization\Enums\ReportingIssue;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Support\ReportingGaps;
use App\Modules\Tenancy\Models\Tenant;

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
function gapNames(ReportingIssue $issue): array
{
    return array_map(fn (Employee $employee): ?string => $employee->name, test()->gaps->page($issue)->items());
}

it('names each issue as the screen shows it, and says what its list holds', function (): void {
    expect(array_map(fn (ReportingIssue $issue): array => [$issue->value, $issue->label()], ReportingIssue::cases()))->toBe([
        ['without_manager', 'Sin jefe'],
        ['roots', 'Raíces'],
        ['inactive_manager', 'Jefe dado de baja'],
    ])
        ->and(ReportingIssue::WithoutManager->hint(3))->toBe('Personas activas sin jefe directo y sin nadie que les reporte.')
        ->and(ReportingIssue::InactiveManager->hint(1))->toBe('Personas activas cuyo jefe directo fue dado de baja.')
        ->and(ReportingIssue::ExtraRoot->hint(0))->toBe('Nadie tiene a alguien que le reporte todavía.')
        ->and(ReportingIssue::ExtraRoot->hint(1))->toBe('Una sola raíz: así debe ser.')
        ->and(ReportingIssue::ExtraRoot->hint(2))->toBe('Hay 2 personas hasta arriba. Normalmente solo hay una.');
});

it('takes anyone as something to fix, except one person at the top', function (): void {
    expect(array_map(fn (int $count): bool => ReportingIssue::WithoutManager->pending($count), [0, 1]))->toBe([false, true])
        ->and(array_map(fn (int $count): bool => ReportingIssue::InactiveManager->pending($count), [0, 1]))->toBe([false, true])
        ->and(array_map(fn (int $count): bool => ReportingIssue::ExtraRoot->pending($count), [0, 1, 2]))->toBe([false, false, true]);
});

it('counts the gaps of the reporting line', function (): void {
    expect($this->gaps->counts())->toBe(['without_manager' => 3, 'roots' => 2, 'inactive_manager' => 1]);
});

it('lists the people of each issue, by name, and nobody else', function (): void {
    expect(gapNames(ReportingIssue::WithoutManager))->toBe(['Suelta A', 'Suelta B', 'Suelta C'])
        ->and(gapNames(ReportingIssue::ExtraRoot))->toBe(['Dir', 'Otra raíz'])
        ->and(gapNames(ReportingIssue::InactiveManager))->toBe(['Huérfana']);
});

it('leaves out whoever left', function (): void {
    // Someone who left with no manager, and one who left under a manager who left, are nobody's gap.
    Employee::factory()->deactivated()->create([...$this->place, 'name' => 'Baja suelta']);
    Employee::factory()->reportingTo($this->gone)->deactivated()->create($this->place);
    // The only report of a person at the top left: that person is on their own now, not a root.
    $alone = Employee::factory()->create([...$this->place, 'name' => 'Suelta D']);
    Employee::factory()->reportingTo($alone)->deactivated()->create($this->place);

    expect($this->gaps->counts())->toBe(['without_manager' => 4, 'roots' => 2, 'inactive_manager' => 1])
        ->and(gapNames(ReportingIssue::WithoutManager))->toBe(['Suelta A', 'Suelta B', 'Suelta C', 'Suelta D'])
        ->and(gapNames(ReportingIssue::ExtraRoot))->toBe(['Dir', 'Otra raíz'])
        ->and(gapNames(ReportingIssue::InactiveManager))->toBe(['Huérfana']);
});

it('leaves out another company', function (): void {
    $stranger = Employee::factory()->create();
    Employee::factory()->reportingTo($stranger)->create();
    $left = Employee::factory()->deactivated()->create();
    Employee::factory()->reportingTo($left)->create();

    expect($this->gaps->counts())->toBe(['without_manager' => 3, 'roots' => 2, 'inactive_manager' => 1])
        ->and(gapNames(ReportingIssue::ExtraRoot))->toBe(['Dir', 'Otra raíz'])
        ->and(gapNames(ReportingIssue::InactiveManager))->toBe(['Huérfana']);
});

it('counts nothing where there is nobody', function (): void {
    workIn(Tenant::factory()->create());

    expect($this->gaps->counts())->toBe(['without_manager' => 0, 'roots' => 0, 'inactive_manager' => 0])
        ->and($this->gaps->page(ReportingIssue::WithoutManager)->total())->toBe(0);
});

it('pages the people of an issue, keeping the query in the links', function (): void {
    Employee::factory()->count(30)->create($this->place);
    request()->merge(['issue' => 'without_manager']);

    $page = $this->gaps->page(ReportingIssue::WithoutManager);

    expect($page->total())->toBe(33)
        ->and($page->perPage())->toBe(25)
        ->and($page->items())->toHaveCount(25)
        ->and($page->nextPageUrl())->toContain('issue=without_manager')
        ->and($this->gaps->page(ReportingIssue::WithoutManager, 10)->items())->toHaveCount(10);
});

it('brings the area and the manager of each person, without a query per row', function (): void {
    Employee::factory()->count(5)->reportingTo($this->gone)->create($this->place);

    // The count, the page, the areas and the managers.
    $this->expectsDatabaseQueryCount(4);

    $people = $this->gaps->page(ReportingIssue::InactiveManager)->items();

    expect($people)->toHaveCount(6)
        ->and($people[0]->manager?->name)->toBe('Se fue')
        ->and($people[0]->area?->name)->toBeString();
});

it('counts the gaps with one query', function (): void {
    $this->expectsDatabaseQueryCount(1);

    $this->gaps->counts();
});
