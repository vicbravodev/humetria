<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Attendance\Actions\BuildAuthorityReport;
use App\Modules\Attendance\Data\AuthorityRow;
use App\Modules\Attendance\Enums\CorrectionStatus;
use App\Modules\Attendance\Enums\MarkMethod;
use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Enums\SignerType;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\AttendancePolicy;
use App\Modules\Attendance\Models\AttendanceSchedule;
use App\Modules\Attendance\Models\CorrectionRequest;
use App\Modules\Attendance\Models\WeeklyConfirmation;
use App\Modules\Attendance\Support\CorrectionHistory;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Attendance\MarkReviewFactory;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\OfflineDeliveries;

mutates(BuildAuthorityReport::class, CorrectionHistory::class);

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2027-02-01 18:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    $this->office = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Oficinas']);
    $this->ana = reportPerson($this->plant, 'Ana López', 'Operadora');
    AttendanceSchedule::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
});

/** A member of the company with the Recursos Humanos role and this name. */
function hrNamed(Tenant $tenant, string $name): User
{
    $user = memberOf($tenant, TenantRole::Hr);
    $user->update(['name' => $name]);

    return $user;
}

/** A correction of `$original` to a local time (null voids it), with its reason. */
function authorityReportCorrection(AttendanceMark $original, ?string $local, string $reason): AttendanceMark
{
    return AttendanceMark::factory()->correcting($original)->create([
        'marked_at' => $local === null ? null : CarbonImmutable::parse($local, 'America/Mexico_City')->utc(), 'reason' => $reason,
    ]);
}

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
    $marks = [
        reportMark($this->ana, MarkType::Start, '2027-01-05 08:00'),
        reportMark($this->ana, MarkType::BreakStart, '2027-01-05 13:00'),
        reportMark($this->ana, MarkType::BreakEnd, '2027-01-05 14:00'),
        reportMark($this->ana, MarkType::End, '2027-01-05 18:00'),
    ];

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
        timezone: 'UTC−06:00 · America/Mexico_City',
        marks: array_map(fn (AttendanceMark $mark): array => ['id' => $mark->id, 'type' => $mark->type->value, 'date' => '2027-01-05', 'time' => $mark->marked_at?->setTimezone('America/Mexico_City')->format('H:i')], $marks),
    )]);
});

it('tells the lateness of each day against the start time and the company\'s tolerance, apart from the flags', function (): void {
    AttendanceSchedule::query()->update(['starts_by_weekday' => [1 => 540, 2 => 540, 3 => 540, 4 => 540, 5 => 540, 6 => null, 7 => null]]);
    AttendancePolicy::factory()->create(['tenant_id' => $this->tenant->id, 'late_tolerance_minutes' => 5]);
    reportDay($this->ana, '2027-01-05 09:20', '2027-01-05 17:20');
    reportDay($this->ana, '2027-01-06 09:05', '2027-01-06 17:05');
    reportDay($this->ana, '2027-01-07 09:06', '2027-01-07 17:06');

    expect(authorityRows($this->plant, '2027-01-04', '2027-01-10'))->sequence(
        fn ($row) => $row->localDate->toBe('2027-01-05')->lateMinutes->toBe(20)->flags->toBe([]),
        fn ($row) => $row->lateMinutes->toBe(0),
        fn ($row) => $row->lateMinutes->toBe(6),
    );
});

describe('with the absences of Vacaciones (criterio 2.6)', function (): void {
    beforeEach(function (): void {
        AttendanceSchedule::query()->update(['starts_by_weekday' => [1 => 540, 2 => 540, 3 => 540, 4 => 540, 5 => 540, 6 => null, 7 => null]]);
    });

    it('has no lateness on a day of approved vacations, says so and flags that the person marked while away', function (): void {
        LeaveRequest::factory()->approved()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'starts_on' => '2027-01-05', 'ends_on' => '2027-01-05', 'working_days' => 1]);
        reportDay($this->ana, '2027-01-05 09:20', '2027-01-05 17:20');
        reportDay($this->ana, '2027-01-06 09:20', '2027-01-06 17:20');

        expect(authorityRows($this->plant, '2027-01-04', '2027-01-10'))->sequence(
            fn ($row) => $row->localDate->toBe('2027-01-05')->lateMinutes->toBeNull()->absence->toBe('vacation')->flags->toBe([BuildAuthorityReport::MARKED_WHILE_AWAY]),
            fn ($row) => $row->localDate->toBe('2027-01-06')->lateMinutes->toBe(20)->absence->toBeNull()->flags->toBe([]),
        );
    });

    it('says leave for any leave, never its type, and counts only what was approved', function (): void {
        $maternity = LeaveType::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Maternidad']);
        LeaveRequest::factory()->ofType($maternity)->approved()->create(['employee_id' => $this->ana->id, 'starts_on' => '2027-01-05', 'ends_on' => '2027-01-05', 'working_days' => 1]);
        LeaveRequest::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'starts_on' => '2027-01-06', 'ends_on' => '2027-01-06', 'working_days' => 1]);
        reportDay($this->ana, '2027-01-05 09:20', '2027-01-05 17:20');
        reportDay($this->ana, '2027-01-06 09:20', '2027-01-06 17:20');

        $rows = authorityRows($this->plant, '2027-01-04', '2027-01-10');

        expect(array_map(fn (AuthorityRow $row): ?string => $row->absence, $rows))->toBe(['leave', null])
            ->and(serialize($rows))->not->toContain('Maternidad');
    });

    it('reads no absence in a company that does not use Vacaciones', function (): void {
        LeaveRequest::factory()->approved()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'starts_on' => '2027-01-05', 'ends_on' => '2027-01-05', 'working_days' => 1]);
        resolve(CompanyModules::class)->syncPlan($this->tenant->id, ['attendance'], CarbonImmutable::now(config('app.display_timezone')));
        reportDay($this->ana, '2027-01-05 09:20', '2027-01-05 17:20');

        expect(authorityRows($this->plant, '2027-01-04', '2027-01-10'))->sequence(
            fn ($row) => $row->lateMinutes->toBe(20)->absence->toBeNull()->flags->toBe([]),
        );
    });
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
            ->corrections->toBe(['hora corregida de 08:20 a 08:00 (por RH): El lector no respondió.', 'marca agregada (fin 17:00, por RH): Olvidó marcar la salida.']),
        fn ($row) => $row->evidence->toBe('Fuerte')->corrections->toBe([]),
        fn ($row) => $row->evidence->toBe('Básica'),
    );
});

const OFFLINE_WITH_PASSKEY = 'Marcó sin conexión (firmó con su llave de acceso)';
const OFFLINE_WITH_DEVICE = 'Marcó sin conexión (con la llave del dispositivo)';
const WAITING_FOR_RH = 'Con marcas sin conexión por verificar';
const VERIFIED_BY_RH = 'Marca sin conexión verificada por RH';

/** The only day of Ana's week in the report: a start made without a connection and an end at the door. */
function offlineDay(Employee $person, bool $qr, string $signer = 'passkey'): AttendanceMark
{
    $delivery = OfflineDeliveries::accepted($person, '2027-01-05 08:00', qr: $qr, with: ['signer_type' => SignerType::from($signer)]);
    reportMark($person, MarkType::End, '2027-01-05 16:00');

    return AttendanceMark::query()->findOrFail($delivery->mark_id);
}

describe('marks made without a connection', function (): void {
    it('says so, with who signed, and weighs its evidence: medium with a QR, basic without one, as long as nobody verified it', function (bool $qr, string $evidence, string $signer, string $text): void {
        offlineDay($this->ana, $qr, $signer);

        expect(authorityRows($this->plant, '2027-01-04', '2027-01-10'))->sequence(
            fn ($row) => $row->evidence->toBe($evidence)->flags->toBe([$text, WAITING_FOR_RH])->start->toBe('08:00'),
        );
    })->with([
        'a QR and a passkey' => [true, 'Media', 'passkey', OFFLINE_WITH_PASSKEY],
        'no QR and the key of the device' => [false, 'Básica', 'device_key', OFFLINE_WITH_DEVICE],
    ]);

    it('stops waiting for RH once it is verified, and says so', function (): void {
        $mark = offlineDay($this->ana, true);
        MarkReviewFactory::new()->createOne(['tenant_id' => $this->tenant->id, 'mark_id' => $mark->id]);

        expect(authorityRows($this->plant, '2027-01-04', '2027-01-10'))->sequence(
            fn ($row) => $row->evidence->toBe('Media')->flags->toBe([OFFLINE_WITH_PASSKEY, VERIFIED_BY_RH]),
        );
    });

    it('takes the last review as the one in force', function (): void {
        $mark = offlineDay($this->ana, true);
        MarkReviewFactory::new()->incorrect()->createOne(['tenant_id' => $this->tenant->id, 'mark_id' => $mark->id]);
        MarkReviewFactory::new()->createOne(['tenant_id' => $this->tenant->id, 'mark_id' => $mark->id]);

        expect(authorityRows($this->plant, '2027-01-04', '2027-01-10'))->sequence(fn ($row) => $row->flags->toBe([OFFLINE_WITH_PASSKEY, VERIFIED_BY_RH]));
    });

    it('keeps saying it was made without a connection after RH corrected it, one correction or two, with the corrected evidence', function (int $corrections): void {
        $mark = offlineDay($this->ana, false, 'device_key');
        MarkReviewFactory::new()->incorrect()->createOne(['tenant_id' => $this->tenant->id, 'mark_id' => $mark->id]);
        $last = authorityReportCorrection($mark, '2027-01-05 08:20', 'Entró a las 8:20.');
        $last = $corrections === 2 ? authorityReportCorrection($last, '2027-01-05 08:30', 'Mejor a las 8:30.') : $last;

        expect(authorityRows($this->plant, '2027-01-04', '2027-01-10'))->sequence(
            fn ($row) => $row->evidence->toBe('Corregida')->flags->toBe([OFFLINE_WITH_DEVICE])->start->toBe($corrections === 2 ? '08:30' : '08:20'),
        );
    })->with(['one correction' => 1, 'a correction of the correction' => 2]);

    it('says nothing of a day made online, nor names RH', function (): void {
        reportDay($this->ana, '2027-01-05 08:00', '2027-01-05 16:00');

        expect(authorityRows($this->plant, '2027-01-04', '2027-01-10'))->sequence(fn ($row) => $row->flags->toBe([]));
    });

    // Decisión del dueño, 2026-11-24: the report names who reviewed each mark made without a connection, and when.
    it('says who verified it and when, at the local time of the center', function (): void {
        $mark = offlineDay($this->ana, true);
        $reviewer = hrNamed($this->tenant, 'Rosa Revisora');
        MarkReviewFactory::new()->createOne(['tenant_id' => $this->tenant->id, 'mark_id' => $mark->id, 'reviewer_id' => $reviewer->id, 'created_at' => '2027-01-06 16:30:00']);

        expect(authorityRows($this->plant, '2027-01-04', '2027-01-10'))->sequence(
            fn ($row) => $row->reviews->toBe([['time' => '08:00', 'decision' => 'verified', 'reviewer' => 'Rosa Revisora', 'reviewedAt' => '2027-01-06 10:30']]),
        );
    });

    it('says who judged it incorrect, with the time the mark had before RH corrected it', function (): void {
        $mark = offlineDay($this->ana, true);
        $reviewer = hrNamed($this->tenant, 'Rosa Revisora');
        MarkReviewFactory::new()->incorrect()->createOne(['tenant_id' => $this->tenant->id, 'mark_id' => $mark->id, 'reviewer_id' => $reviewer->id, 'created_at' => '2027-01-06 16:30:00']);
        authorityReportCorrection($mark, '2027-01-05 08:20', 'Entró a las 8:20.');

        expect(authorityRows($this->plant, '2027-01-04', '2027-01-10'))->sequence(
            fn ($row) => $row->start->toBe('08:20')->reviews->toBe([['time' => '08:00', 'decision' => 'incorrect', 'reviewer' => 'Rosa Revisora', 'reviewedAt' => '2027-01-06 10:30']]),
        );
    });

    it('says who judged it incorrect when RH voided it instead of correcting it, on the day it was part of', function (): void {
        $mark = offlineDay($this->ana, true);
        MarkReviewFactory::new()->incorrect()->createOne(['tenant_id' => $this->tenant->id, 'mark_id' => $mark->id, 'reviewer_id' => hrNamed($this->tenant, 'Rosa Revisora')->id, 'created_at' => '2027-01-06 16:30:00']);
        authorityReportCorrection($mark, null, 'No estaba en la planta.');

        expect(authorityRows($this->plant, '2027-01-04', '2027-01-10'))->sequence(
            fn ($row) => $row->start->toBeNull()->corrections->toHaveCount(1)
                ->reviews->toBe([['time' => '08:00', 'decision' => 'incorrect', 'reviewer' => 'Rosa Revisora', 'reviewedAt' => '2027-01-06 10:30']]),
        );
    });

    it('names the reviewer of the review in force, the last', function (): void {
        $mark = offlineDay($this->ana, true);
        MarkReviewFactory::new()->incorrect()->createOne(['tenant_id' => $this->tenant->id, 'mark_id' => $mark->id, 'reviewer_id' => hrNamed($this->tenant, 'Primera Revisora')->id]);
        MarkReviewFactory::new()->createOne(['tenant_id' => $this->tenant->id, 'mark_id' => $mark->id, 'reviewer_id' => hrNamed($this->tenant, 'Segunda Revisora')->id]);

        $reviews = authorityRows($this->plant, '2027-01-04', '2027-01-10')[0]->reviews;

        expect($reviews)->toHaveCount(1)->and($reviews[0]['reviewer'])->toBe('Segunda Revisora')->and($reviews[0]['decision'])->toBe('verified');
    });

    it('has no reviewer nor time on a mark nobody has reviewed, nor on one made online', function (): void {
        offlineDay($this->ana, true);
        $bob = reportPerson($this->plant, 'Bob Díaz');
        reportDay($bob, '2027-01-05 08:00', '2027-01-05 16:00');

        expect(authorityRows($this->plant, '2027-01-04', '2027-01-10'))->sequence(
            fn ($row) => $row->reviews->toBe([]),
            fn ($row) => $row->reviews->toBe([]),
        );
    });

    it('does not carry a verification over to a mark RH changed afterwards', function (): void {
        $mark = offlineDay($this->ana, true);
        MarkReviewFactory::new()->createOne(['tenant_id' => $this->tenant->id, 'mark_id' => $mark->id, 'reviewer_id' => hrNamed($this->tenant, 'Rosa Revisora')->id]);
        authorityReportCorrection($mark, '2027-01-05 08:20', 'Entró a las 8:20.');

        expect(authorityRows($this->plant, '2027-01-04', '2027-01-10'))->sequence(fn ($row) => $row->reviews->toBe([]));
    });

    it('says the reviewer no longer has access when they are not a member of the company, and never names someone of another company', function (): void {
        $mark = offlineDay($this->ana, true);
        $left = User::factory()->create(['name' => 'Revisora Secreta']);
        $other = hrNamed(Tenant::factory()->create(), 'Revisora De Otra Empresa');
        MarkReviewFactory::new()->createOne(['tenant_id' => $this->tenant->id, 'mark_id' => $mark->id, 'reviewer_id' => $left->id]);
        $first = authorityRows($this->plant, '2027-01-04', '2027-01-10');
        MarkReviewFactory::new()->createOne(['tenant_id' => $this->tenant->id, 'mark_id' => $mark->id, 'reviewer_id' => $other->id]);
        $second = authorityRows($this->plant, '2027-01-04', '2027-01-10');

        expect($first[0]->reviews[0]['reviewer'])->toBe('Persona de RH (ya no tiene acceso)')
            ->and($second[0]->reviews[0]['reviewer'])->toBe('Persona de RH (ya no tiene acceso)')
            ->and(json_encode([$first, $second], JSON_THROW_ON_ERROR))->not->toContain('Secreta')->not->toContain('Otra Empresa');
    });

    it('never carries the note of the person nor the one of RH', function (): void {
        $mark = offlineDay($this->ana, true);
        MarkReviewFactory::new()->incorrect('Nota privada de RH.')->createOne(['tenant_id' => $this->tenant->id, 'mark_id' => $mark->id, 'reviewer_id' => hrNamed($this->tenant, 'Rosa Revisora')->id]);
        authorityReportCorrection($mark, '2027-01-05 08:20', 'Entró a las 8:20.');

        expect(json_encode(authorityRows($this->plant, '2027-01-04', '2027-01-10'), JSON_THROW_ON_ERROR))->not->toContain('Nota privada');
    });

    it('reads the names of the reviewers in no query per mark', function (): void {
        $reviewers = array_map(fn (int $n): User => hrNamed($this->tenant, "Revisor {$n}"), range(1, 5));
        MarkReviewFactory::new()->createOne(['tenant_id' => $this->tenant->id, 'mark_id' => offlineDay($this->ana, true)->id, 'reviewer_id' => $reviewers[0]->id]);
        authorityRows($this->plant, '2027-01-04', '2027-01-10');
        DB::flushQueryLog();
        DB::enableQueryLog();
        authorityRows($this->plant, '2027-01-04', '2027-01-10');
        $one = count(DB::getQueryLog());

        foreach (range(1, 5) as $n) {
            $other = reportPerson($this->plant, "Persona {$n}");
            $delivery = OfflineDeliveries::accepted($other, '2027-01-05 08:00', qr: true);
            reportMark($other, MarkType::End, '2027-01-05 16:00');
            MarkReviewFactory::new()->createOne(['tenant_id' => $this->tenant->id, 'mark_id' => $delivery->mark_id, 'reviewer_id' => $reviewers[$n - 1]->id]);
        }
        DB::flushQueryLog();
        authorityRows($this->plant, '2027-01-04', '2027-01-10');

        expect(count(DB::getQueryLog()))->toBe($one);
    });

    it('reads the same queries whatever the number of marks made without a connection', function (): void {
        offlineDay($this->ana, true);
        authorityRows($this->plant, '2027-01-04', '2027-01-10');
        DB::flushQueryLog();
        DB::enableQueryLog();
        authorityRows($this->plant, '2027-01-04', '2027-01-10');
        $one = count(DB::getQueryLog());

        foreach (range(1, 5) as $n) {
            $other = reportPerson($this->plant, "Persona {$n}");
            OfflineDeliveries::accepted($other, '2027-01-05 08:00', qr: $n % 2 === 0);
            reportMark($other, MarkType::End, '2027-01-05 16:00');
        }
        DB::flushQueryLog();
        authorityRows($this->plant, '2027-01-04', '2027-01-10');

        expect(count(DB::getQueryLog()))->toBe($one);
    });
});

it('names the days marked in the field: strong with a good position, medium without one', function (): void {
    reportMark($this->ana, MarkType::Start, '2027-01-05 08:00', MarkMethod::Field);
    reportMark($this->ana, MarkType::End, '2027-01-05 16:00', MarkMethod::Field);
    reportMark($this->ana, MarkType::Start, '2027-01-06 08:00', MarkMethod::Field);
    reportMark($this->ana, MarkType::End, '2027-01-06 16:00', MarkMethod::FieldUnlocated);
    reportDay($this->ana, '2027-01-07 08:00', '2027-01-07 16:00');

    expect(authorityRows($this->plant, '2027-01-04', '2027-01-10'))->sequence(
        fn ($row) => $row->evidence->toBe('Fuerte')->flags->toBe(['Marcó en campo']),
        fn ($row) => $row->evidence->toBe('Media')->flags->toBe(['Marcó en campo']),
        fn ($row) => $row->evidence->toBe('Fuerte')->flags->toBe([]),
    );
});

describe('what was corrected', function (): void {
    it('says the time a correction replaced, in local times', function (): void {
        $start = reportMark($this->ana, MarkType::Start, '2027-01-05 08:12');
        authorityReportCorrection($start, '2027-01-05 08:00', 'Llegó a las 8.');
        reportMark($this->ana, MarkType::End, '2027-01-05 16:00');

        expect(authorityRows($this->plant, '2027-01-04', '2027-01-10'))->sequence(fn ($row) => $row
            ->start->toBe('08:00')->corrections->toBe(['hora corregida de 08:12 a 08:00 (por RH): Llegó a las 8.']));
    });

    it('lists each step of a chain of corrections, in order', function (): void {
        $start = reportMark($this->ana, MarkType::Start, '2027-01-05 08:20');
        $first = authorityReportCorrection($start, '2027-01-05 08:10', 'Primera corrección.');
        authorityReportCorrection($first, '2027-01-05 08:00', 'Segunda corrección.');
        reportMark($this->ana, MarkType::End, '2027-01-05 16:00');

        expect(authorityRows($this->plant, '2027-01-04', '2027-01-10'))->sequence(fn ($row) => $row->corrections->toBe([
            'hora corregida de 08:20 a 08:10 (por RH): Primera corrección.',
            'hora corregida de 08:10 a 08:00 (por RH): Segunda corrección.',
        ]));
    });

    it('names who asked by their role only: the person, through a request, or RH directly', function (): void {
        $start = reportMark($this->ana, MarkType::Start, '2027-01-05 08:12');
        $correction = authorityReportCorrection($start, '2027-01-05 08:00', 'Pedí corregir mi entrada.');
        CorrectionRequest::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'mark_id' => $start->id,
            'status' => CorrectionStatus::Approved, 'resolved_by' => $correction->corrected_by, 'resolved_at' => now(), 'resulting_mark_id' => $correction->id]);
        reportMark($this->ana, MarkType::End, '2027-01-05 16:00');

        expect(authorityRows($this->plant, '2027-01-04', '2027-01-10'))->sequence(fn ($row) => $row
            ->corrections->toBe(['hora corregida de 08:12 a 08:00 (a petición de la persona): Pedí corregir mi entrada.']));
    });

    it('keeps a voided end on its day, with its time and reason', function (): void {
        reportMark($this->ana, MarkType::Start, '2027-01-05 08:00');
        authorityReportCorrection(reportMark($this->ana, MarkType::End, '2027-01-05 18:05'), null, 'Marcó la salida por error.');

        expect(authorityRows($this->plant, '2027-01-04', '2027-01-10'))->sequence(fn ($row) => $row
            ->end->toBeNull()->evidence->toBe('Corregida')
            ->corrections->toBe(['marca anulada (fin 18:05, por RH): Marcó la salida por error.'])
            ->flags->toBe(['Sin marca de fin']));
    });

    it('keeps a voided end of a night on the day the night started', function (): void {
        reportMark($this->ana, MarkType::Start, '2027-01-10 22:00');
        authorityReportCorrection(reportMark($this->ana, MarkType::End, '2027-01-11 06:00'), null, 'Salió a otra hora.');

        expect(authorityRows($this->plant, '2027-01-04', '2027-01-11'))->sequence(fn ($row) => $row
            ->localDate->toBe('2027-01-10')->corrections->toBe(['marca anulada (fin 06:00, por RH): Salió a otra hora.']));
    });

    it('keeps a night whose start and end were both voided on one row, the day it started', function (): void {
        // Saturday 9 January 22:00 to Sunday 06:00, both voided.
        authorityReportCorrection(reportMark($this->ana, MarkType::Start, '2027-01-09 22:00'), null, 'No trabajó esa noche.');
        authorityReportCorrection(reportMark($this->ana, MarkType::End, '2027-01-10 06:00'), null, 'Tampoco salió.');

        expect(authorityRows($this->plant, '2027-01-04', '2027-01-10'))->toHaveCount(1)->sequence(fn ($row) => $row
            ->localDate->toBe('2027-01-09')->start->toBeNull()->workedMinutes->toBe(0)
            ->corrections->toBe([
                'marca anulada (inicio 22:00, por RH): No trabajó esa noche.',
                'marca anulada (fin 06:00, por RH): Tampoco salió.',
            ]));
    });

    it('does not hand a voided end of a night to a shift that began later the next day', function (): void {
        authorityReportCorrection(reportMark($this->ana, MarkType::Start, '2027-01-09 22:00'), null, 'No trabajó esa noche.');
        authorityReportCorrection(reportMark($this->ana, MarkType::End, '2027-01-10 06:00'), null, 'Tampoco salió.');
        reportMark($this->ana, MarkType::Start, '2027-01-10 14:00');
        reportMark($this->ana, MarkType::End, '2027-01-10 22:00');

        expect(authorityRows($this->plant, '2027-01-04', '2027-01-10'))->sequence(
            fn ($row) => $row->localDate->toBe('2027-01-09')->corrections->toBe([
                'marca anulada (inicio 22:00, por RH): No trabajó esa noche.',
                'marca anulada (fin 06:00, por RH): Tampoco salió.',
            ]),
            fn ($row) => $row->localDate->toBe('2027-01-10')->start->toBe('14:00')->end->toBe('22:00')->corrections->toBe([]),
        );
    });

    it('calls a correction that gives a time back to a voided mark «marca agregada»', function (): void {
        $void = authorityReportCorrection(reportMark($this->ana, MarkType::Start, '2027-01-05 08:12'), null, 'Marcó por error.');
        authorityReportCorrection($void, '2027-01-05 08:00', 'Sí vino, a las 8.');
        reportMark($this->ana, MarkType::End, '2027-01-05 16:00');

        expect(authorityRows($this->plant, '2027-01-04', '2027-01-10'))->sequence(fn ($row) => $row
            // In order of the times they speak of.
            ->start->toBe('08:00')->corrections->toBe([
                'marca agregada (inicio 08:00, por RH): Sí vino, a las 8.',
                'marca anulada (inicio 08:12, por RH): Marcó por error.',
            ]));
    });

    it('keeps a person whose only start was voided, the day showing the voided start', function (): void {
        authorityReportCorrection(reportMark($this->ana, MarkType::Start, '2027-01-05 08:00'), null, 'No vino ese día.');

        expect(authorityRows($this->plant, '2027-01-04', '2027-01-10'))->sequence(fn ($row) => $row
            ->personName->toBe('Ana López')->localDate->toBe('2027-01-05')->start->toBeNull()->workedMinutes->toBe(0)
            ->evidence->toBe('Corregida')
            ->corrections->toBe(['marca anulada (inicio 08:00, por RH): No vino ese día.'])
            ->flags->toBe(['Sin marcas vigentes']));
    });
});

it('says a day that started today and has no end yet is in course, not missing its end', function (): void {
    // Now is Monday 1 February 2027, 12:00 in Mexico City.
    reportMark($this->ana, MarkType::Start, '2027-02-01 08:00');
    reportMark($this->ana, MarkType::Start, '2027-01-29 08:00');

    expect(array_map(fn (AuthorityRow $row): array => $row->flags, authorityRows($this->plant, '2027-01-25', '2027-02-01')))->toBe([
        ['Sin marca de fin'],
        ['En curso'],
    ]);
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
                'type' => $type->value, 'method' => MarkMethod::QrAndGeofence->value, 'timezone' => 'America/Mexico_City', 'marked_at' => CarbonImmutable::parse($local, 'America/Mexico_City')->utc(), 'created_at' => now(),
            ];
        }
    }
    AttendanceMark::query()->insert($rows);
    DB::flushQueryLog();
    DB::enableQueryLog();

    $names = array_map(fn (AuthorityRow $row): string => $row->personName, authorityRows($this->plant, '2027-01-04', '2027-01-10'));

    expect($names)->toBe(array_map(fn (int $index): string => sprintf('Persona %03d', $index), range(0, 449)))
        // Who has days and their order, the center's zone, then nine reads for each of the three chunks of 200 (what was corrected, where the marks come from and who was away among them).
        ->and(count(DB::getQueryLog()))->toBe(3 + 3 * 9);
});

it('reads its people in chunks of the size of config/attendance.php (200 by default)', function (): void {
    expect(config('attendance.report.chunk_size'))->toBe(200);
    $bob = reportPerson($this->plant, 'Bob Díaz');
    reportMark($this->ana, MarkType::Start, '2027-01-05 08:00');
    reportMark($bob, MarkType::Start, '2027-01-05 08:00');
    $queries = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        authorityRows($this->plant, '2027-01-04', '2027-01-10');

        return count(DB::getQueryLog());
    };

    $whole = $queries();
    config(['attendance.report.chunk_size' => 1]);

    expect($queries())->toBeGreaterThan($whole);
});

describe('in the zone of each mark (design of time zones §3.8)', function (): void {
    /** A mark of `$person` at an instant in UTC, taken in `$zone`. */
    function zonedReportMark(Employee $person, MarkType $type, string $utc, string $zone): AttendanceMark
    {
        return AttendanceMark::factory()->create([
            'tenant_id' => $person->tenant_id, 'employee_id' => $person->id, 'work_center_id' => $person->work_center_id,
            'type' => $type, 'marked_at' => CarbonImmutable::parse($utc, 'UTC'), 'timezone' => $zone,
        ]);
    }

    it('names the zone of each day with its offset', function (): void {
        $tijuana = WorkCenter::factory()->for($this->tenant)->create(['timezone' => 'America/Tijuana']);
        $bob = reportPerson($tijuana, 'Bob Díaz');
        zonedReportMark($bob, MarkType::Start, '2027-01-05 14:00', 'America/Tijuana');
        zonedReportMark($bob, MarkType::End, '2027-01-05 22:00', 'America/Tijuana');

        expect(authorityRows($tijuana, '2027-01-04', '2027-01-10')[0])
            ->localDate->toBe('2027-01-05')->start->toBe('06:00')->end->toBe('14:00')
            ->timezone->toBe('UTC−08:00 · America/Tijuana');
    });

    it('gives the two offsets of a day across the repeated hour, so two 01:30 are told apart', function (): void {
        $this->travelTo(CarbonImmutable::parse('2026-11-03 18:00', 'UTC'));
        $tijuana = WorkCenter::factory()->for($this->tenant)->create(['timezone' => 'America/Tijuana']);
        $bob = reportPerson($tijuana, 'Bob Díaz');
        // 01:30 of summer time, then 01:30 again an hour later, of winter time.
        zonedReportMark($bob, MarkType::Start, '2026-11-01 08:30', 'America/Tijuana');
        zonedReportMark($bob, MarkType::End, '2026-11-01 09:30', 'America/Tijuana');

        expect(authorityRows($tijuana, '2026-10-26', '2026-11-01')[0])
            ->start->toBe('01:30')->end->toBe('01:30')->workedMinutes->toBe(60)
            ->timezone->toBe('UTC−07:00 a UTC−08:00 · America/Tijuana');
    });

    it('says nothing of a zone for a day with no marks left', function (): void {
        authorityReportCorrection(reportMark($this->ana, MarkType::Start, '2027-01-05 09:00'), null, 'Marcó por error.');

        expect(authorityRows($this->plant, '2027-01-04', '2027-01-10')[0]->timezone)->toBe('');
    });
});
