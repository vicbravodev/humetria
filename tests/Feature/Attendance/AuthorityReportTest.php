<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Attendance\Actions\BuildAuthorityReport;
use App\Modules\Attendance\Data\AuthorityRow;
use App\Modules\Attendance\Enums\MarkMethod;
use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\AttendanceSchedule;
use App\Modules\Attendance\Models\WeeklyConfirmation;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

mutates(BuildAuthorityReport::class);

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2027-02-01 18:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    $this->office = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Oficinas']);
    $this->ana = reportPerson($this->plant, 'Ana López', 'Operadora');
    AttendanceSchedule::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
});

function reportPerson(WorkCenter $center, string $name, string $jobTitle = 'Operadora'): Employee
{
    return EmployeeFactory::new()->create(['tenant_id' => $center->tenant_id, 'work_center_id' => $center->id, 'name' => $name, 'job_title' => $jobTitle]);
}

/** A mark of `$person` at a local time of Mexico City, in `$center` (theirs by default). */
function reportMark(Employee $person, MarkType $type, string $local, MarkMethod $method = MarkMethod::QrAndGeofence, ?WorkCenter $center = null): AttendanceMark
{
    return AttendanceMark::factory()->create([
        'tenant_id' => $person->tenant_id, 'employee_id' => $person->id, 'work_center_id' => $center->id ?? $person->work_center_id,
        'type' => $type, 'marked_at' => CarbonImmutable::parse($local, 'America/Mexico_City')->utc(), 'method' => $method,
        'latitude' => '19.432712', 'longitude' => '-99.133298', 'accuracy_m' => 12.5, 'distance_m' => 11.25,
    ]);
}

/** A day of `$person` from a start to an end (local times), with no break. */
function reportDay(Employee $person, string $start, string $end, ?WorkCenter $center = null): void
{
    reportMark($person, MarkType::Start, $start, center: $center);
    reportMark($person, MarkType::End, $end, center: $center);
}

/**
 * @return list<AuthorityRow>
 */
function authorityRows(WorkCenter $center, string $from, string $to): array
{
    return resolve(BuildAuthorityReport::class)->handle($center->id, CarbonImmutable::parse($from), CarbonImmutable::parse($to))->values()->all();
}

it('gives one row per person-day with local times, the breaks and the hours as «Mi semana» splits them', function (): void {
    reportMark($this->ana, MarkType::Start, '2027-01-05 08:00');
    reportMark($this->ana, MarkType::BreakStart, '2027-01-05 13:00');
    reportMark($this->ana, MarkType::BreakEnd, '2027-01-05 14:00');
    reportMark($this->ana, MarkType::End, '2027-01-05 18:00');

    expect(authorityRows($this->plant, '2027-01-04', '2027-01-10'))->toEqual([new AuthorityRow(
        employeeId: $this->ana->id,
        personName: 'Ana López',
        jobTitle: 'Operadora',
        localDate: '2027-01-05',
        start: '08:00',
        end: '18:00',
        endDate: '2027-01-05',
        breakMinutes: 60,
        workedMinutes: 540,
        ordinaryMinutes: 480,
        doubleMinutes: 60,
        tripleMinutes: 0,
        restDayMinutes: 0,
        evidence: 'Fuerte',
        corrections: [],
        confirmation: 'sin confirmar',
        weekDigest: '',
        flags: [],
    )]);
});

it('puts a day on the local date of its start, even when its UTC date is the next one', function (): void {
    // 23:50 in Mexico City is 05:50 UTC of the next day.
    reportDay($this->ana, '2027-01-06 23:50', '2027-01-07 07:00');

    expect(authorityRows($this->plant, '2027-01-04', '2027-01-10'))->sequence(fn ($row) => $row
        ->localDate->toBe('2027-01-06')->start->toBe('23:50')->end->toBe('07:00')->endDate->toBe('2027-01-07')->workedMinutes->toBe(430));
});

it('keeps a night from Sunday 22:00 to Monday 06:00 on Sunday, a rest day of the schedule', function (): void {
    reportDay($this->ana, '2027-01-10 22:00', '2027-01-11 06:00');

    expect(authorityRows($this->plant, '2027-01-04', '2027-01-11'))->sequence(fn ($row) => $row
        ->localDate->toBe('2027-01-10')->end->toBe('06:00')->endDate->toBe('2027-01-11')
        ->ordinaryMinutes->toBe(0)->restDayMinutes->toBe(480)->doubleMinutes->toBe(0));
});

it('leaves the end empty and says so for a day with a start and no end', function (): void {
    reportMark($this->ana, MarkType::Start, '2027-01-05 08:00');

    expect(authorityRows($this->plant, '2027-01-04', '2027-01-10'))->sequence(fn ($row) => $row
        ->end->toBeNull()->endDate->toBeNull()->workedMinutes->toBe(0)->flags->toBe(['Sin marca de fin']));
});

it('lists each correction of the day with its reason, and the day\'s evidence is the weakest of its marks', function (): void {
    $start = reportMark($this->ana, MarkType::Start, '2027-01-05 08:20');
    AttendanceMark::factory()->correcting($start)->create(['marked_at' => CarbonImmutable::parse('2027-01-05 08:00', 'America/Mexico_City')->utc(), 'reason' => 'El lector no respondió.']);
    // A mark the person forgot, added by RH.
    AttendanceMark::factory()->create([
        'tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'work_center_id' => $this->plant->id, 'type' => MarkType::End,
        'marked_at' => CarbonImmutable::parse('2027-01-05 17:00', 'America/Mexico_City')->utc(), 'method' => MarkMethod::Correction, 'corrected_by' => User::factory(), 'reason' => 'Olvidó marcar la salida.',
    ]);
    reportDay($this->ana, '2027-01-06 08:00', '2027-01-06 16:00');
    reportMark($this->ana, MarkType::Start, '2027-01-07 08:00', MarkMethod::Geofence);
    reportMark($this->ana, MarkType::End, '2027-01-07 16:00', MarkMethod::TrustedBrowser);

    expect(authorityRows($this->plant, '2027-01-04', '2027-01-10'))->sequence(
        fn ($row) => $row->start->toBe('08:00')->end->toBe('17:00')->evidence->toBe('Corregida')
            ->corrections->toBe(['hora corregida: El lector no respondió.', 'marca agregada: Olvidó marcar la salida.']),
        fn ($row) => $row->evidence->toBe('Fuerte')->corrections->toBe([]),
        fn ($row) => $row->evidence->toBe('Básica'),
    );
});

it('gives each day the answer to its week, and the digest only of a confirmed one', function (): void {
    reportDay($this->ana, '2027-01-05 08:00', '2027-01-05 16:00');
    reportDay($this->ana, '2027-01-12 08:00', '2027-01-12 16:00');
    reportDay($this->ana, '2027-01-19 08:00', '2027-01-19 16:00');
    reportDay($this->ana, '2027-01-26 08:00', '2027-01-26 16:00');
    WeeklyConfirmation::factory()->confirmed()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'week_start' => '2027-01-04', 'digest' => str_repeat('a', 64)]);
    WeeklyConfirmation::factory()->disputed()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'week_start' => '2027-01-11', 'digest' => str_repeat('b', 64)]);
    WeeklyConfirmation::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'week_start' => '2027-01-18']);

    expect(array_map(fn (AuthorityRow $row): array => [$row->confirmation, $row->weekDigest], authorityRows($this->plant, '2027-01-04', '2027-01-31')))->toBe([
        ['confirmado', str_repeat('a', 64)],
        ['objetada', ''],
        ['sin confirmar', ''],
        ['sin confirmar', ''],
    ]);
});

it('fills the week\'s double-pay cap with its earliest overtime, then pays the rest triple, even when the period starts mid-week', function (): void {
    // 2027: up to 9 h a week paid double. Tuesday, Wednesday and Thursday 12 h each: 4 h of overtime a day, 12 h in the week.
    reportDay($this->ana, '2027-01-05 07:00', '2027-01-05 19:00');
    reportDay($this->ana, '2027-01-06 07:00', '2027-01-06 19:00');
    reportDay($this->ana, '2027-01-07 07:00', '2027-01-07 19:00');
    $split = fn (AuthorityRow $row): array => [$row->localDate, $row->ordinaryMinutes, $row->doubleMinutes, $row->tripleMinutes, $row->flags];

    expect(array_map($split, authorityRows($this->plant, '2027-01-04', '2027-01-10')))->toBe([
        ['2027-01-05', 480, 240, 0, []],
        ['2027-01-06', 480, 240, 0, []],
        ['2027-01-07', 480, 60, 180, ['Rebasó el tope de horas extra de la semana']],
    ])
        ->and(array_map($split, authorityRows($this->plant, '2027-01-07', '2027-01-07')))->toBe([
            ['2027-01-07', 480, 60, 180, ['Rebasó el tope de horas extra de la semana']],
        ]);
});

it('includes a deactivated person who has days in the period', function (): void {
    $gone = EmployeeFactory::new()->deactivated()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'name' => 'Beto Ex']);
    reportDay($gone, '2027-01-05 08:00', '2027-01-05 16:00');
    reportDay($this->ana, '2027-01-05 08:00', '2027-01-05 16:00');

    expect(array_map(fn (AuthorityRow $row): string => $row->personName, authorityRows($this->plant, '2027-01-04', '2027-01-10')))->toBe(['Ana López', 'Beto Ex']);
});

it('puts each day of a person who moved in the center where it started, and leaves out the days outside the period', function (): void {
    reportDay($this->ana, '2027-01-05 08:00', '2027-01-05 16:00');
    reportDay($this->ana, '2027-01-06 08:00', '2027-01-06 16:00', $this->office);
    reportDay($this->ana, '2027-01-11 08:00', '2027-01-11 16:00');

    expect(array_map(fn (AuthorityRow $row): string => $row->localDate, authorityRows($this->plant, '2027-01-04', '2027-01-10')))->toBe(['2027-01-05'])
        ->and(array_map(fn (AuthorityRow $row): string => $row->localDate, authorityRows($this->office, '2027-01-04', '2027-01-10')))->toBe(['2027-01-06']);
});

it('gives nothing of another company\'s center', function (): void {
    reportDay($this->ana, '2027-01-05 08:00', '2027-01-05 16:00');
    $globex = Tenant::factory()->create();
    $theirs = WorkCenter::factory()->for($globex)->create();

    expect(authorityRows($theirs, '2027-01-04', '2027-01-10'))->toBe([]);
});

it('streams hundreds of people in chunks, every row in order, in the same queries per chunk', function (): void {
    $people = EmployeeFactory::new()->count(450)->sequence(fn ($sequence) => ['name' => sprintf('Persona %03d', $sequence->index)])
        ->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
    $rows = [];
    foreach ($people as $person) {
        foreach ([[MarkType::Start, '2027-01-05 08:00'], [MarkType::End, '2027-01-05 16:00']] as [$type, $local]) {
            $rows[] = [
                'id' => (string) Str::uuid(), 'tenant_id' => $this->tenant->id, 'employee_id' => $person->id, 'work_center_id' => $this->plant->id,
                'type' => $type->value, 'method' => MarkMethod::QrAndGeofence->value, 'marked_at' => CarbonImmutable::parse($local, 'America/Mexico_City')->utc(), 'created_at' => now(),
            ];
        }
    }
    AttendanceMark::query()->insert($rows);
    DB::flushQueryLog();
    DB::enableQueryLog();

    $names = array_map(fn (AuthorityRow $row): string => $row->personName, authorityRows($this->plant, '2027-01-04', '2027-01-10'));

    expect($names)->toBe(array_map(fn (int $index): string => sprintf('Persona %03d', $index), range(0, 449)))
        // Who has days and their order, then six reads for each of the three chunks of 200.
        ->and(count(DB::getQueryLog()))->toBe(2 + 3 * 6);
});
