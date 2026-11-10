<?php

declare(strict_types=1);

use App\Modules\Leave\Contracts\Absences;
use App\Modules\Leave\Data\AbsenceData;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Leave\Support\CompanyAbsences;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;

mutates(CompanyAbsences::class);

beforeEach(function (): void {
    workIn($this->tenant = Tenant::factory()->create());
    $this->ana = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
});

/**
 * A request of a person in the given state; by default the vacations of the factory, from Monday 9 to Friday 20 November 2026.
 *
 * @param  array<string, mixed>  $attributes
 */
function absenceOf(Employee $person, string $state = 'approved', array $attributes = []): LeaveRequest
{
    $factory = LeaveRequest::factory();

    return ($state === 'requested' ? $factory : $factory->{$state}())->create(['tenant_id' => $person->tenant_id, 'employee_id' => $person->id, ...$attributes]);
}

/**
 * @param  list<string>  $employeeIds
 * @return list<string>
 */
function absentOn(string $day, array $employeeIds): array
{
    return resolve(Absences::class)->absentOn(CarbonImmutable::parse($day), $employeeIds);
}

/**
 * @param  list<string>  $employeeIds
 * @return list<array{string, string, string, string}>
 */
function absencesBetween(string $from, string $to, array $employeeIds): array
{
    return array_map(
        fn (AbsenceData $absence): array => [$absence->employeeId, $absence->from->toDateString(), $absence->to->toDateString(), $absence->kind],
        resolve(Absences::class)->between(CarbonImmutable::parse($from), CarbonImmutable::parse($to), $employeeIds),
    );
}

it('says a person is absent every day of their approved vacations, weekends too, and no day outside them', function (string $day, bool $absent): void {
    absenceOf($this->ana);

    expect(absentOn($day, [$this->ana->id]))->toBe($absent ? [$this->ana->id] : []);
})->with([
    'the day before' => ['2026-11-08', false],
    'the first day' => ['2026-11-09', true],
    'a Saturday in between' => ['2026-11-14', true],
    'the last day' => ['2026-11-20', true],
    'the day after' => ['2026-11-21', false],
]);

it('takes the day as a date, whatever its hour', function (): void {
    absenceOf($this->ana);

    expect(resolve(Absences::class)->absentOn(CarbonImmutable::parse('2026-11-20 23:30:00'), [$this->ana->id]))->toBe([$this->ana->id]);
});

it('counts only what was approved or already enjoyed', function (string $state, bool $counts): void {
    absenceOf($this->ana, $state);

    expect(absentOn('2026-11-10', [$this->ana->id]))->toBe($counts ? [$this->ana->id] : [])
        ->and(absencesBetween('2026-11-01', '2026-11-30', [$this->ana->id]))->toHaveCount($counts ? 1 : 0);
})->with([['approved', true], ['taken', true], ['requested', false], ['rejected', false], ['cancelled', false]]);

it('answers only for the people asked, once each', function (): void {
    $beto = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
    $carla = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
    absenceOf($this->ana);
    absenceOf($beto);
    // Two absences on the same day are still one person absent.
    absenceOf($beto, 'approved', ['leave_type_id' => LeaveType::factory()->create(['tenant_id' => $this->tenant->id])->id, 'had_balance' => null, 'starts_on' => '2026-11-10', 'ends_on' => '2026-11-10', 'working_days' => 1]);

    expect(absentOn('2026-11-10', [$beto->id, $carla->id]))->toBe([$beto->id]);
});

it('gives the whole stretch of each absence that touches the range, even when the range cuts it', function (string $from, string $to, bool $touches): void {
    absenceOf($this->ana);

    expect(absencesBetween($from, $to, [$this->ana->id]))->toBe($touches ? [[$this->ana->id, '2026-11-09', '2026-11-20', 'vacation']] : []);
})->with([
    'cut on both sides' => ['2026-11-12', '2026-11-13', true],
    'ends on its first day' => ['2026-11-01', '2026-11-09', true],
    'starts on its last day' => ['2026-11-20', '2026-11-30', true],
    'ends the day before' => ['2026-11-01', '2026-11-08', false],
    'starts the day after' => ['2026-11-21', '2026-11-30', false],
]);

it('says leave for any type of leave, never its name, and gives the absences in order', function (): void {
    $maternity = LeaveType::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Maternidad']);
    absenceOf($this->ana, 'approved', ['starts_on' => '2026-11-23', 'ends_on' => '2026-11-24', 'working_days' => 2]);
    LeaveRequest::factory()->ofType($maternity)->approved()->create(['employee_id' => $this->ana->id, 'starts_on' => '2026-11-02', 'ends_on' => '2026-11-06', 'working_days' => 5]);

    $absences = resolve(Absences::class)->between(CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-11-30'), [$this->ana->id]);

    expect(absencesBetween('2026-11-01', '2026-11-30', [$this->ana->id]))->toBe([
        [$this->ana->id, '2026-11-02', '2026-11-06', 'leave'],
        [$this->ana->id, '2026-11-23', '2026-11-24', 'vacation'],
    ])->and(array_keys(get_object_vars($absences[0])))->toBe(['employeeId', 'from', 'to', 'kind'])
        ->and(serialize($absences))->not->toContain('Maternidad');
});

it('gives nothing for people of another company', function (): void {
    $other = Tenant::factory()->create();
    $stranger = Employee::factory()->create(['tenant_id' => $other->id]);
    absenceOf($stranger);

    expect(absentOn('2026-11-10', [$stranger->id]))->toBe([])
        ->and(absencesBetween('2026-11-01', '2026-11-30', [$stranger->id]))->toBe([]);
});

it('asks the database once for any number of people, and not at all for nobody', function (): void {
    $people = Employee::factory()->count(50)->create(['tenant_id' => $this->tenant->id]);
    $ids = array_values($people->map(fn (Employee $person): string => $person->id)->all());
    absenceOf($people[0]);
    absenceOf($people[49]);

    $this->expectsDatabaseQueryCount(2);

    expect(absentOn('2026-11-10', $ids))->toEqualCanonicalizing([$ids[0], $ids[49]])
        ->and(absencesBetween('2026-11-01', '2026-11-30', $ids))->toHaveCount(2)
        ->and(absentOn('2026-11-10', []))->toBe([])
        ->and(absencesBetween('2026-11-01', '2026-11-30', []))->toBe([]);
});
