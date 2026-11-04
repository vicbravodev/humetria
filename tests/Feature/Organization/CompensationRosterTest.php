<?php

declare(strict_types=1);

use App\Modules\Organization\Contracts\CompensationRoster;
use App\Modules\Organization\Data\CompensationPerson;
use App\Modules\Organization\Data\HeadcountCell;
use App\Modules\Organization\Data\RosterGaps;
use App\Modules\Organization\Enums\StaffGroup;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Models\JobGrade;
use App\Modules\Organization\Support\CompanyCompensationRoster;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;

mutates(CompanyCompensationRoster::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->grade = JobGrade::factory()->create(['tenant_id' => $this->tenant->id, 'category' => 'Operador', 'level' => 'A']);
    $this->otherGrade = JobGrade::factory()->create(['tenant_id' => $this->tenant->id, 'category' => 'Operador', 'level' => 'B']);
    $this->area = Area::factory()->create(['tenant_id' => $this->tenant->id]);
});

/** A person the calculator can cost, in the tenant of the test; `$attributes` overrides any column. */
function costedPerson(Tenant $tenant, JobGrade $grade, array $attributes = []): Employee
{
    return Employee::factory()->create([
        'tenant_id' => $tenant->id, 'job_grade_id' => $grade->id, 'area_id' => null, 'hired_on' => '2020-03-15', 'staff_group' => 'unionized',
        'state' => 'QUE', 'employee_number' => 'E-'.fake()->unique()->numerify('#####'),
        ...$attributes,
    ]);
}

function roster(): CompensationRoster
{
    return resolve(CompensationRoster::class);
}

function asOf(string $date = '2026-06-30'): CarbonImmutable
{
    return CarbonImmutable::parse($date);
}

it('counts by category and level, area, group, state and hire month in one query, without loading employees', function (): void {
    costedPerson($this->tenant, $this->grade, ['area_id' => $this->area->id, 'hired_on' => '2020-03-01']);
    costedPerson($this->tenant, $this->grade, ['area_id' => $this->area->id, 'hired_on' => '2020-03-31']);
    costedPerson($this->tenant, $this->grade, ['area_id' => $this->area->id, 'hired_on' => '2020-04-01']);
    costedPerson($this->tenant, $this->grade, ['area_id' => $this->area->id, 'hired_on' => '2020-03-15', 'staff_group' => 'trusted']);
    costedPerson($this->tenant, $this->grade, ['area_id' => null, 'hired_on' => '2020-03-15', 'state' => null]);
    costedPerson($this->tenant, $this->otherGrade, ['area_id' => $this->area->id, 'hired_on' => '2020-03-15']);
    costedPerson($this->tenant, $this->grade, ['area_id' => $this->area->id, 'hired_on' => '2020-03-15', 'state' => 'JAL']);
    workIn($this->tenant);

    $this->expectsDatabaseQueryCount(1);
    $cells = roster()->cells(asOf(), []);

    $summary = array_map(fn (HeadcountCell $cell): array => [
        $cell->jobGradeId, $cell->areaId, $cell->group, $cell->state, $cell->hireMonth->toDateString(), $cell->count,
    ], $cells);

    expect($cells)->toHaveCount(6)->each->toBeInstanceOf(HeadcountCell::class)
        ->and($summary)->toContain([$this->grade->id, $this->area->id, StaffGroup::Unionized, 'QUE', '2020-03-01', 2])
        ->toContain([$this->grade->id, $this->area->id, StaffGroup::Unionized, 'QUE', '2020-04-01', 1])
        ->toContain([$this->grade->id, $this->area->id, StaffGroup::Trusted, 'QUE', '2020-03-01', 1])
        ->toContain([$this->grade->id, null, StaffGroup::Unionized, null, '2020-03-01', 1])
        ->toContain([$this->otherGrade->id, $this->area->id, StaffGroup::Unionized, 'QUE', '2020-03-01', 1])
        ->toContain([$this->grade->id, $this->area->id, StaffGroup::Unionized, 'JAL', '2020-03-01', 1]);
});

it('gives the hire month as the first day of the month at midnight UTC', function (): void {
    costedPerson($this->tenant, $this->grade, ['hired_on' => '2020-03-31']);
    workIn($this->tenant);

    $cell = roster()->cells(asOf(), [])[0];

    expect($cell->hireMonth)->toEqual(CarbonImmutable::parse('2020-03-01 00:00:00', 'UTC'))
        ->and($cell->hireMonth->timezoneName)->toBe('UTC');
});

it('leaves out whoever is hired after the date of the scenario, and keeps whoever is hired that very day', function (): void {
    costedPerson($this->tenant, $this->grade, ['hired_on' => '2026-06-30']);
    costedPerson($this->tenant, $this->grade, ['hired_on' => '2026-07-01']);
    costedPerson($this->tenant, $this->grade, ['hired_on' => '2026-08-15']);
    workIn($this->tenant);

    $cells = roster()->cells(asOf('2026-06-30'), []);

    expect($cells)->toHaveCount(1)
        ->and($cells[0]->count)->toBe(1)
        ->and($cells[0]->hireMonth->toDateString())->toBe('2026-06-01')
        ->and(roster()->cells(asOf('2026-06-29'), []))->toBe([])
        ->and(array_sum(array_map(fn (HeadcountCell $cell): int => $cell->count, roster()->cells(asOf('2026-12-31'), []))))->toBe(3);
});

it('ignores the time of day of the date of the scenario', function (): void {
    costedPerson($this->tenant, $this->grade, ['hired_on' => '2026-06-30']);
    workIn($this->tenant);

    expect(roster()->cells(CarbonImmutable::parse('2026-06-30 00:00:00'), []))->toHaveCount(1)
        ->and(roster()->cells(CarbonImmutable::parse('2026-06-30 23:59:59'), []))->toHaveCount(1);
});

it('leaves overrides out of the cells', function (): void {
    costedPerson($this->tenant, $this->grade, ['employee_number' => 'E-001']);
    costedPerson($this->tenant, $this->grade, ['employee_number' => 'E-002']);
    costedPerson($this->tenant, $this->grade, ['employee_number' => 'E-003']);
    costedPerson($this->tenant, $this->grade, ['employee_number' => null]);
    workIn($this->tenant);

    expect(roster()->cells(asOf(), [])[0]->count)->toBe(4)
        ->and(roster()->cells(asOf(), ['E-002'])[0]->count)->toBe(3)
        ->and(roster()->cells(asOf(), ['E-001', 'E-003', 'E-404'])[0]->count)->toBe(2)
        ->and(roster()->cells(asOf(), ['e-001'])[0]->count)->toBe(3)
        ->and(roster()->cells(asOf(), ['E-001', 'E-002', 'E-003'])[0]->count)->toBe(1);
});

it('keeps out of the cells whoever lacks a category and level, a hire date or a group', function (): void {
    costedPerson($this->tenant, $this->grade);
    Employee::factory()->create(['tenant_id' => $this->tenant->id, 'job_grade_id' => null, 'hired_on' => '2020-03-15', 'staff_group' => 'unionized']);
    Employee::factory()->create(['tenant_id' => $this->tenant->id, 'job_grade_id' => $this->grade->id, 'hired_on' => null, 'staff_group' => 'unionized']);
    Employee::factory()->create(['tenant_id' => $this->tenant->id, 'job_grade_id' => $this->grade->id, 'hired_on' => '2020-03-15', 'staff_group' => null]);
    workIn($this->tenant);

    expect(roster()->cells(asOf(), []))->toHaveCount(1)
        ->and(roster()->cells(asOf(), [])[0]->count)->toBe(1);
});

it('gives nothing for an empty roster', function (): void {
    workIn($this->tenant);

    expect(roster()->cells(asOf(), []))->toBe([])
        ->and(roster()->individuals(['E-001']))->toBe([])
        ->and(roster()->individuals([]))->toBe([])
        ->and(roster()->gaps())->toEqual(new RosterGaps(total: 0, costed: 0, missing: [], missingWithoutNumber: 0, inactive: 0));
});

it('ignores deactivated people', function (): void {
    costedPerson($this->tenant, $this->grade, ['employee_number' => 'E-001']);
    costedPerson($this->tenant, $this->grade, ['employee_number' => 'E-002', 'deactivated_at' => now()]);
    Employee::factory()->deactivated()->create(['tenant_id' => $this->tenant->id, 'job_grade_id' => null, 'employee_number' => 'E-003']);
    workIn($this->tenant);

    expect(roster()->cells(asOf(), [])[0]->count)->toBe(1)
        ->and(array_keys(roster()->individuals(['E-001', 'E-002'])))->toBe(['E-001'])
        ->and(roster()->gaps())->toEqual(new RosterGaps(total: 1, costed: 1, missing: [], missingWithoutNumber: 0, inactive: 2));
});

it('gives the data of a person by company ID, without name, contact, gender or internal id', function (): void {
    $employee = costedPerson($this->tenant, $this->grade, [
        'employee_number' => 'E-001', 'area_id' => $this->area->id, 'hired_on' => '2019-02-28', 'staff_group' => 'trusted', 'state' => 'JAL',
        'name' => 'Ana López', 'email' => 'ana@acme.mx', 'gender' => 'female',
    ]);
    costedPerson($this->tenant, $this->grade, ['employee_number' => 'E-002']);
    workIn($this->tenant);

    $people = roster()->individuals(['E-001', 'E-404']);

    expect($people)->toHaveCount(1)->toHaveKey('E-001')
        ->and($people['E-001'])->toBeInstanceOf(CompensationPerson::class)
        ->and($people['E-001']->employeeNumber)->toBe('E-001')
        ->and($people['E-001']->jobGradeId)->toBe($this->grade->id)
        ->and($people['E-001']->areaId)->toBe($this->area->id)
        ->and($people['E-001']->group)->toBe(StaffGroup::Trusted)
        ->and($people['E-001']->state)->toBe('JAL')
        ->and($people['E-001']->hiredOn->toDateString())->toBe('2019-02-28')
        ->and(array_keys(get_object_vars($people['E-001'])))->toBe(['employeeNumber', 'jobGradeId', 'areaId', 'group', 'state', 'hiredOn'])
        ->and(json_encode($people))->not->toContain($employee->id)->not->toContain('Ana')->not->toContain('acme.mx');
});

it('finds a person by company ID ignoring case, and leaves out whoever cannot be costed', function (): void {
    costedPerson($this->tenant, $this->grade, ['employee_number' => 'E-001']);
    costedPerson($this->tenant, $this->grade, ['employee_number' => 'E-002', 'hired_on' => null]);
    costedPerson($this->tenant, $this->grade, ['employee_number' => 'E-003', 'staff_group' => null]);
    Employee::factory()->create(['tenant_id' => $this->tenant->id, 'job_grade_id' => null, 'employee_number' => 'E-004']);
    workIn($this->tenant);

    expect(array_keys(roster()->individuals(['e-001', 'E-002', 'E-003', 'E-004'])))->toBe(['E-001']);
});

it('reports who lacks grade or hire date by company ID, never by name or id', function (): void {
    costedPerson($this->tenant, $this->grade, ['employee_number' => 'E-001']);
    $noGrade = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'job_grade_id' => null, 'hired_on' => '2020-03-15', 'staff_group' => 'unionized', 'employee_number' => 'E-020', 'name' => 'Beatriz Sosa']);
    Employee::factory()->create(['tenant_id' => $this->tenant->id, 'job_grade_id' => $this->grade->id, 'hired_on' => null, 'staff_group' => 'unionized', 'employee_number' => 'E-010']);
    Employee::factory()->create(['tenant_id' => $this->tenant->id, 'job_grade_id' => $this->grade->id, 'hired_on' => '2020-03-15', 'staff_group' => null, 'employee_number' => 'E-030']);
    Employee::factory()->create(['tenant_id' => $this->tenant->id, 'job_grade_id' => null, 'hired_on' => null, 'staff_group' => null, 'employee_number' => null, 'name' => 'Carlos Ruiz']);
    Employee::factory()->create(['tenant_id' => $this->tenant->id, 'job_grade_id' => null, 'employee_number' => null, 'name' => 'Diana Paz']);
    workIn($this->tenant);

    $gaps = roster()->gaps();

    expect($gaps->total)->toBe(6)
        ->and($gaps->costed)->toBe(1)
        ->and($gaps->missing)->toBe(['E-010', 'E-020', 'E-030'])
        ->and($gaps->missingWithoutNumber)->toBe(2)
        ->and($gaps->inactive)->toBe(0)
        ->and(json_encode($gaps))->not->toContain($noGrade->id)->not->toContain('Beatriz')->not->toContain('Carlos');
});

it('keeps accounts apart', function (): void {
    $other = Tenant::factory()->create();
    $otherGrade = JobGrade::factory()->create(['tenant_id' => $other->id]);
    costedPerson($this->tenant, $this->grade, ['employee_number' => 'E-001']);
    costedPerson($other, $otherGrade, ['employee_number' => 'E-001']);
    costedPerson($other, $otherGrade, ['employee_number' => 'E-002']);
    Employee::factory()->create(['tenant_id' => $other->id, 'job_grade_id' => null, 'employee_number' => 'E-900']);
    Employee::factory()->deactivated()->create(['tenant_id' => $other->id, 'employee_number' => 'E-901']);

    workIn($this->tenant);
    $mine = roster();
    workIn($other);
    $theirs = roster();

    workIn($this->tenant);
    expect($mine->cells(asOf(), []))->toHaveCount(1)
        ->and($mine->cells(asOf(), [])[0]->jobGradeId)->toBe($this->grade->id)
        ->and($mine->cells(asOf(), [])[0]->count)->toBe(1)
        ->and(array_keys($mine->individuals(['E-001', 'E-002'])))->toBe(['E-001'])
        ->and($mine->individuals(['E-001'])['E-001']->jobGradeId)->toBe($this->grade->id)
        ->and($mine->gaps())->toEqual(new RosterGaps(total: 1, costed: 1, missing: [], missingWithoutNumber: 0, inactive: 0));

    workIn($other);
    expect($theirs->cells(asOf(), [])[0]->count)->toBe(2)
        ->and($theirs->gaps())->toEqual(new RosterGaps(total: 3, costed: 2, missing: ['E-900'], missingWithoutNumber: 0, inactive: 1));
});

it('serves a union account the same as a company account', function (): void {
    $union = Tenant::factory()->union()->create();
    $grade = JobGrade::factory()->create(['tenant_id' => $union->id]);
    Employee::factory()->create([
        'tenant_id' => $union->id, 'work_center_id' => null, 'area_id' => null, 'gender' => null, 'name' => null, 'email' => null,
        'mobile' => null, 'job_title' => null, 'employee_number' => 'S-001', 'job_grade_id' => $grade->id, 'hired_on' => '2018-09-10',
        'staff_group' => 'unionized', 'state' => null,
    ]);
    workIn($union);

    $cells = roster()->cells(asOf(), []);

    expect($cells)->toHaveCount(1)
        ->and($cells[0]->areaId)->toBeNull()
        ->and($cells[0]->state)->toBeNull()
        ->and($cells[0]->hireMonth->toDateString())->toBe('2018-09-01')
        ->and(roster()->individuals(['S-001'])['S-001']->areaId)->toBeNull()
        ->and(roster()->gaps()->costed)->toBe(1);
});
