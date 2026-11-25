<?php

declare(strict_types=1);

use App\Modules\Attendance\Enums\MarkMethod;
use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Http\Controllers\ReportController;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\WeeklyConfirmation;
use App\Modules\Attendance\Support\ReportPages;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Organization\Data\RosterQuery;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Attendance\MarkReviewFactory;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Testing\AssertableInertia;
use Tests\Support\OfflineDeliveries;

mutates(ReportController::class, ReportPages::class);

/** The queries of the report's page with a center, whatever the number of people or days: what was corrected and its view to the audit trail included; the pass over everyone for the figures and the one for the days of the page (nine each, who was away and where the marks come from included: 26 before the second), and the zone of the center, read once. */
const REPORT_PAGE_QUERIES = 37;

beforeEach(function (): void {
    // Monday 1 February 2027, 12:00 in Mexico City: the last week that ended is 25 to 31 January.
    $this->travelTo(CarbonImmutable::parse('2027-02-01 18:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'name' => 'Ana López', 'email' => 'operadora@example.test', 'job_title' => 'Operadora']);
    $this->bob = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'name' => 'Bob Díaz', 'email' => 'almacenista@example.test', 'job_title' => 'Almacenista']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
});

/** A day of `$person` from a start to an end, local times of Mexico City, marked at the plant's door. */
function pageDay(Employee $person, ?string $start, ?string $end): AttendanceMark
{
    $first = null;
    foreach (array_filter([[MarkType::Start, $start], [MarkType::End, $end]], fn (array $mark): bool => $mark[1] !== null) as [$type, $local]) {
        $mark = AttendanceMark::factory()->create([
            'tenant_id' => $person->tenant_id, 'employee_id' => $person->id, 'work_center_id' => $person->work_center_id, 'type' => $type,
            'marked_at' => CarbonImmutable::parse($local, 'America/Mexico_City')->utc(), 'latitude' => '19.432712', 'longitude' => '-99.133298', 'accuracy_m' => 12.5, 'distance_m' => 11.25,
        ]);
        $first ??= $mark;
    }

    return $first;
}

function authorityReportUrl(array $query = [], string $tenant = 'acme'): string
{
    return route('tenant.attendance.report', [$tenant, ...$query]);
}

/** The company's roster, counting how many people each read of people by id asks for: the days of the report are built per read. */
function spyOnRosterReads(): ArrayObject
{
    $reads = new ArrayObject;
    app()->instance(Roster::class, new class(resolve(Roster::class), $reads) implements Roster
    {
        public function __construct(private readonly Roster $roster, private readonly ArrayObject $reads) {}

        public function activeIn(string $workCenterId): array
        {
            return $this->roster->activeIn($workCenterId);
        }

        public function activeIdsIn(string $workCenterId): array
        {
            return $this->roster->activeIdsIn($workCenterId);
        }

        public function activeIdsInMany(array $workCenterIds): array
        {
            return $this->roster->activeIdsInMany($workCenterIds);
        }

        public function find(string $employeeId): ?RosterEntry
        {
            return $this->roster->find($employeeId);
        }

        public function findMany(array $employeeIds): array
        {
            $this->reads->append(count($employeeIds));

            return $this->roster->findMany($employeeIds);
        }

        public function page(RosterQuery $query, int $perPage = 25, string $pageName = 'page'): LengthAwarePaginator
        {
            return $this->roster->page($query, $perPage, $pageName);
        }

        public function matchingIds(RosterQuery $query): array
        {
            return $this->roster->matchingIds($query);
        }

        public function idsByEmployeeNumber(array $numbers): array
        {
            return $this->roster->idsByEmployeeNumber($numbers);
        }

        public function idsByEmail(string $email): array
        {
            return $this->roster->idsByEmail($email);
        }
    });

    return $reads;
}

describe('the page', function (): void {
    it('asks for a center first, with the last week that ended as the period', function (): void {
        $this->actingAs($this->hr);

        $this->get(authorityReportUrl())->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('attendance/report')
            ->where('center', null)
            ->where('period', ['from' => '2027-01-25', 'to' => '2027-01-31'])
            ->where('filters', ['q' => '', 'show' => 'all'])
            ->where('summary', null)
            ->where('people', null)
            ->where('longPeriod', null)
            ->where('limits', ['screenDays' => 93, 'exportDays' => 366])
            ->where('exportUrl', null));
    });

    it('offers the usual periods: this week up to today, the last week that ended and the last calendar month', function (): void {
        $this->actingAs($this->hr);

        $this->get(authorityReportUrl())->assertInertia(fn (AssertableInertia $page) => $page->where('presets', [
            ['key' => 'this-week', 'label' => 'Esta semana', 'from' => '2027-02-01', 'to' => '2027-02-01'],
            ['key' => 'last-week', 'label' => 'Semana pasada', 'from' => '2027-01-25', 'to' => '2027-01-31'],
            ['key' => 'last-month', 'label' => 'Último mes', 'from' => '2027-01-01', 'to' => '2027-01-31'],
        ]));
    });

    it('starts the last month on the first of the previous month even when today is the 31st', function (): void {
        $this->travelTo(CarbonImmutable::parse('2027-03-31 18:00:00', 'UTC'));
        $this->actingAs($this->hr);

        $this->get(authorityReportUrl())->assertInertia(fn (AssertableInertia $page) => $page
            ->where('presets.2', ['key' => 'last-month', 'label' => 'Último mes', 'from' => '2027-02-01', 'to' => '2027-02-28']));
    });

    it('does not count a person whose days were only marked in the field as one with warnings', function (): void {
        foreach ([[MarkType::Start, '2027-01-26 08:00'], [MarkType::End, '2027-01-26 16:00']] as [$type, $local]) {
            AttendanceMark::factory()->create([
                'tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'work_center_id' => $this->plant->id, 'type' => $type, 'method' => MarkMethod::Field,
                'marked_at' => CarbonImmutable::parse($local, 'America/Mexico_City')->utc(),
            ]);
        }
        $this->actingAs($this->hr);

        $this->get(authorityReportUrl(['center' => $this->plant->id, 'from' => '2027-01-25', 'to' => '2027-01-31']))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('summary.flaggedPeople', 0)
            ->where('people.data.0.days.0.flags', ['Marcó en campo'])
            ->where('people.data.0.days.0.evidence', 'Fuerte'));
    });

    it('shows the center\'s period: its figures, the people page by page with their days, and the link to the CSV', function (): void {
        $first = pageDay($this->ana, '2027-01-26 08:00', '2027-01-26 18:00');
        $corrected = pageDay($this->ana, '2027-01-27 08:20', '2027-01-27 16:00');
        AttendanceMark::factory()->correcting($corrected)->create(['marked_at' => CarbonImmutable::parse('2027-01-27 08:00', 'America/Mexico_City')->utc(), 'reason' => 'El lector falló.']);
        WeeklyConfirmation::factory()->confirmed()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'week_start' => '2027-01-25']);
        pageDay($this->bob, '2027-01-28 09:00', null);
        $this->actingAs($this->hr);

        $response = $this->get(authorityReportUrl(['center' => $this->plant->id, 'from' => '2027-01-25', 'to' => '2027-01-31']))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('center', ['id' => $this->plant->id, 'name' => 'Planta Norte'])
            ->where('period', ['from' => '2027-01-25', 'to' => '2027-01-31'])
            ->where('summary', [
                'people' => 2, 'days' => 3, 'workedMinutes' => 1080, 'confirmedPercent' => 67, 'withCorrections' => 1, 'unconfirmed' => 1,
                'confirmedDays' => 2, 'ordinaryMinutes' => 960, 'overtimeMinutes' => 120, 'overTwelveDays' => 0, 'withoutEndDays' => 1, 'flaggedPeople' => 1, 'unconfirmedPeople' => 1, 'toVerify' => 0,
            ])
            ->where('people.total', 2)
            ->where('people.per_page', 25)
            ->where('people.data.0.name', 'Ana López')
            ->where('people.data.0.jobTitle', 'Operadora')
            ->where('people.data.0.days.0', [
                'date' => '2027-01-26', 'start' => '08:00', 'end' => '18:00', 'endDate' => '2027-01-26', 'breakMinutes' => 0, 'workedMinutes' => 600,
                'ordinaryMinutes' => 480, 'doubleMinutes' => 120, 'tripleMinutes' => 0, 'restDayMinutes' => 0, 'evidence' => 'Fuerte',
                'corrections' => [], 'confirmation' => 'confirmado', 'flags' => [], 'lateMinutes' => null, 'timezone' => 'UTC−06:00 · America/Mexico_City', 'absence' => null,
                'marks' => [
                    ['id' => $first->id, 'type' => 'start', 'date' => '2027-01-26', 'time' => '08:00'],
                    ['id' => AttendanceMark::query()->where('employee_id', $this->ana->id)->where('type', MarkType::End)->orderBy('marked_at')->value('id'), 'type' => 'end', 'date' => '2027-01-26', 'time' => '18:00'],
                ],
                'reviews' => [],
            ])
            // The marks in force: the correction, not the mark it replaced.
            ->where('people.data.0.days.1.marks.0.time', '08:00')
            ->where('people.data.0.days.1.lateMinutes', null)
            ->where('people.data.0.days.1.corrections', ['hora corregida de 08:20 a 08:00 (por RH): El lector falló.'])
            ->where('people.data.1.days.0.flags', ['Sin marca de fin'])
            ->where('generatedAt', ['utc' => '2027-02-01 18:00', 'local' => '2027-02-01 12:00'])
            ->where('timezone', ['value' => 'America/Mexico_City', 'label' => 'Hora del Centro, UTC−6 · Ciudad de México'])
            ->where('longPeriod', null)
            ->where('exportUrl', route('tenant.attendance.report.export', ['acme', 'center' => $this->plant->id, 'from' => '2027-01-25', 'to' => '2027-01-31'])));

        expect($response->getContent())->not->toContain('19.4327')->not->toContain('latitude')->not->toContain('longitude')
            ->not->toContain('accuracy')->not->toContain('distance')->not->toContain('credential_id')->not->toContain('credentialId');
    });

    it('counts the days with marks made without a connection that wait for RH, and points at the person; a verified one only informs', function (): void {
        OfflineDeliveries::accepted($this->ana, '2027-01-26 08:00', qr: true);
        pageDay($this->ana, null, '2027-01-26 16:00');
        $verified = OfflineDeliveries::accepted($this->bob, '2027-01-27 08:00');
        pageDay($this->bob, null, '2027-01-27 16:00');
        MarkReviewFactory::new()->createOne(['tenant_id' => $this->tenant->id, 'mark_id' => $verified->mark_id]);
        $this->actingAs($this->hr);

        $this->get(authorityReportUrl(['center' => $this->plant->id, 'from' => '2027-01-25', 'to' => '2027-01-31']))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('summary.toVerify', 1)->where('summary.flaggedPeople', 1)
            ->where('people.data.0.days.0.flags', ['Marcó sin conexión (firmó con su llave de acceso)', 'Con marcas sin conexión por verificar'])
            ->where('people.data.1.days.0.flags', ['Marcó sin conexión (firmó con su llave de acceso)', 'Marca sin conexión verificada por RH'])
            ->where('people.data.0.days.0.evidence', 'Media'));
        $this->get(authorityReportUrl(['center' => $this->plant->id, 'from' => '2027-01-25', 'to' => '2027-01-31', 'show' => 'flagged']))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('people.total', 1)->where('people.data.0.name', 'Ana López'));
    });

    // Decisión del dueño, 2026-11-24: the page names who reviewed each mark made without a connection, and when.
    it('says on each day who reviewed its marks made without a connection, what they decided and when, and nothing else of the review', function (): void {
        $verified = OfflineDeliveries::accepted($this->ana, '2027-01-26 08:00', qr: true);
        pageDay($this->ana, null, '2027-01-26 16:00');
        OfflineDeliveries::accepted($this->bob, '2027-01-27 08:00');
        pageDay($this->bob, null, '2027-01-27 16:00');
        $this->hr->update(['name' => 'Rosa Revisora']);
        MarkReviewFactory::new()->incorrect('Nota privada de RH.')->createOne(['tenant_id' => $this->tenant->id, 'mark_id' => $verified->mark_id, 'reviewer_id' => $this->hr->id, 'created_at' => '2027-01-28 16:30:00']);
        AttendanceMark::factory()->correcting(AttendanceMark::query()->findOrFail($verified->mark_id))->create(['marked_at' => CarbonImmutable::parse('2027-01-26 08:20', 'America/Mexico_City')->utc(), 'reason' => 'Entró a las 8:20.']);
        $this->actingAs($this->hr);

        $response = $this->get(authorityReportUrl(['center' => $this->plant->id, 'from' => '2027-01-25', 'to' => '2027-01-31']))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('people.data.0.days.0.reviews', [['time' => '08:00', 'decision' => 'incorrect', 'reviewer' => 'Rosa Revisora', 'reviewedAt' => '2027-01-28 10:30']])
            ->where('people.data.1.days.0.reviews', []));

        expect($response->getContent())->not->toContain('Nota privada');
    });

    it('says which day the person was away with vacations or a leave, never its type', function (): void {
        $maternity = LeaveType::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Maternidad']);
        LeaveRequest::factory()->ofType($maternity)->approved()->create(['employee_id' => $this->ana->id, 'starts_on' => '2027-01-26', 'ends_on' => '2027-01-26', 'working_days' => 1]);
        pageDay($this->ana, '2027-01-26 08:00', '2027-01-26 16:00');
        $this->actingAs($this->hr);

        $response = $this->get(authorityReportUrl(['center' => $this->plant->id]))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('people.data.0.days.0.absence', 'leave')
            ->where('people.data.0.days.0.flags', ['Marcó durante una ausencia']));

        expect($response->getContent())->not->toContain('Maternidad');
    });

    it('shows an empty period as such, with no people', function (): void {
        $this->actingAs($this->hr);

        $this->get(authorityReportUrl(['center' => $this->plant->id]))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('summary', [
                'people' => 0, 'days' => 0, 'workedMinutes' => 0, 'confirmedPercent' => 0, 'withCorrections' => 0, 'unconfirmed' => 0,
                'confirmedDays' => 0, 'ordinaryMinutes' => 0, 'overtimeMinutes' => 0, 'overTwelveDays' => 0, 'withoutEndDays' => 0, 'flaggedPeople' => 0, 'unconfirmedPeople' => 0, 'toVerify' => 0,
            ])
            ->where('people.total', 0));
    });

    it('pages the people 25 at a time and searches them by name, the figures of all of them', function (): void {
        // Named, with their email too, not left to Faker: the search reads name and email, and a random «diaz» in either would match it.
        $names = array_map(fn (int $n): string => sprintf('Persona %02d', $n), range(1, 25));
        foreach (EmployeeFactory::new()->count(25)->sequence(...array_map(fn (string $name): array => ['name' => $name, 'email' => strtolower(str_replace(' ', '-', $name)).'@example.test'], $names))->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]) as $person) {
            pageDay($person, '2027-01-26 08:00', '2027-01-26 16:00');
        }
        pageDay($this->bob, '2027-01-26 08:00', '2027-01-26 16:00');
        $this->actingAs($this->hr);

        $this->get(authorityReportUrl(['center' => $this->plant->id, 'page' => 2]))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('people.total', 26)->has('people.data', 1)->where('people.current_page', 2));
        $this->get(authorityReportUrl(['center' => $this->plant->id, 'q' => 'DIAZ']))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filters', ['q' => 'DIAZ', 'show' => 'all'])
            ->where('people.total', 1)
            ->where('people.data.0.name', 'Bob Díaz')
            ->where('summary.people', 26));
    });

    it('builds the days of only the people of the page, with the figures of all of them', function (): void {
        $names = array_map(fn (int $n): string => sprintf('Persona %02d', $n), range(1, 30));
        $people = EmployeeFactory::new()->count(30)->sequence(...array_map(fn (string $name): array => ['name' => $name, 'email' => strtolower(str_replace(' ', '-', $name)).'@example.test'], $names))->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
        foreach ($people as $n => $person) {
            // The last five have a day with no end: «con avisos».
            pageDay($person, '2027-01-26 08:00', $n < 25 ? '2027-01-26 16:00' : null);
            pageDay($person, '2027-01-27 08:00', '2027-01-27 16:00');
        }
        $this->actingAs($this->hr);
        $period = ['center' => $this->plant->id, 'from' => '2027-01-25', 'to' => '2027-01-31'];
        $reads = spyOnRosterReads();

        $this->get(authorityReportUrl($period))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('summary.people', 30)->where('summary.days', 60)->where('summary.workedMinutes', 25 * 2 * 480 + 5 * 480)
            ->where('summary.flaggedPeople', 5)->where('summary.withoutEndDays', 5)
            ->has('people.data', 25)->where('people.data.24.name', 'Persona 25')->has('people.data.24.days', 2));

        // The first read is the pass over everyone for the figures; the second is the days, of the 25 listed.
        expect($reads->getArrayCopy())->toBe([30, 25]);

        $this->get(authorityReportUrl([...$period, 'page' => 2]))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('people.data', 5)->where('people.data.0.name', 'Persona 26')->has('people.data.0.days', 2)->where('people.data.0.days.0.flags', ['Sin marca de fin'])
            ->where('summary.days', 60));
        $this->get(authorityReportUrl([...$period, 'show' => 'flagged']))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('people.total', 5)->has('people.data', 5)->where('people.data.0.name', 'Persona 26')->where('people.data.0.days.0.flags', ['Sin marca de fin'])
            ->where('summary.people', 30)->where('summary.flaggedPeople', 5));
    });

    it('reads the report in the same number of queries, however many people and days', function (int $more): void {
        foreach (EmployeeFactory::new()->count($more)->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]) as $person) {
            pageDay($person, '2027-01-26 08:00', '2027-01-26 17:00');
            pageDay($person, '2027-01-27 08:00', '2027-01-27 17:00');
        }
        pageDay($this->ana, '2027-01-26 08:00', '2027-01-26 17:00');
        $this->actingAs($this->hr);

        $this->expectsDatabaseQueryCount(REPORT_PAGE_QUERIES);
        $this->get(authorityReportUrl(['center' => $this->plant->id]))->assertOk();
    })->with(['3 people' => [2], '40 people' => [39]]);
});

describe('naming the reviewers', function (): void {
    it('reads the names of whoever reviewed in the same extra queries, however many reviewed marks and reviewers', function (int $more): void {
        foreach (EmployeeFactory::new()->count($more)->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]) as $person) {
            $delivery = OfflineDeliveries::accepted($person, '2027-01-26 08:00', qr: true);
            pageDay($person, null, '2027-01-26 17:00');
            MarkReviewFactory::new()->createOne(['tenant_id' => $this->tenant->id, 'mark_id' => $delivery->mark_id, 'reviewer_id' => memberOf($this->tenant, TenantRole::Hr)->id]);
        }
        pageDay($this->ana, '2027-01-26 08:00', '2027-01-26 17:00');
        $this->actingAs($this->hr);

        // The company's members, asked once for the request (the company, its roles and its users), and nothing per mark nor per reviewer.
        $this->expectsDatabaseQueryCount(REPORT_PAGE_QUERIES + 3);
        $this->get(authorityReportUrl(['center' => $this->plant->id]))->assertOk();
    })->with(['3 people' => [2], '40 people' => [39]]);
});

describe('before showing it to an inspection', function (): void {
    it('counts what makes the report look weak: days over 12 h, days with no end, and the people they belong to', function (): void {
        pageDay($this->ana, '2027-01-26 06:00', '2027-01-26 19:00');
        pageDay($this->ana, '2027-01-27 08:00', '2027-01-27 16:00');
        pageDay($this->bob, '2027-01-28 09:00', null);
        $this->actingAs($this->hr);

        $this->get(authorityReportUrl(['center' => $this->plant->id, 'from' => '2027-01-25', 'to' => '2027-01-31']))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('summary.overTwelveDays', 1)
            ->where('summary.withoutEndDays', 1)
            ->where('summary.flaggedPeople', 2)
            ->where('summary.unconfirmedPeople', 2)
            ->where('summary.confirmedDays', 0));
    });

    it('counts a person once, whatever the days of theirs that are flagged or unconfirmed', function (): void {
        pageDay($this->ana, '2027-01-26 06:00', '2027-01-26 19:00');
        pageDay($this->ana, '2027-01-27 06:00', '2027-01-27 19:00');
        $this->actingAs($this->hr);

        $this->get(authorityReportUrl(['center' => $this->plant->id, 'from' => '2027-01-25', 'to' => '2027-01-31']))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('summary.overTwelveDays', 2)->where('summary.flaggedPeople', 1)->where('summary.unconfirmedPeople', 1));
    });

    it('shows only the people with flags, or only those with a week no one answered, the figures still of all of them', function (): void {
        pageDay($this->ana, '2027-01-26 08:00', '2027-01-26 16:00');
        WeeklyConfirmation::factory()->confirmed()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'week_start' => '2027-01-25']);
        pageDay($this->bob, '2027-01-28 09:00', null);
        $this->actingAs($this->hr);
        $period = ['center' => $this->plant->id, 'from' => '2027-01-25', 'to' => '2027-01-31'];

        foreach (['flagged', 'unconfirmed'] as $show) {
            $this->get(authorityReportUrl([...$period, 'show' => $show]))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.show', $show)
                ->where('people.total', 1)
                ->where('people.data.0.name', 'Bob Díaz')
                ->where('summary.people', 2));
        }
        $this->get(authorityReportUrl([...$period, 'show' => 'all']))->assertInertia(fn (AssertableInertia $page) => $page->where('people.total', 2));
    });

    it('does not count a day still going as a warning: a person whose only flag is «En curso» is not «con avisos»', function (): void {
        // Today is Monday 1 February 2027: Ana started at 08:00 and has no end yet; Bob had a clean day.
        pageDay($this->ana, '2027-02-01 08:00', null);
        pageDay($this->bob, '2027-01-26 08:00', '2027-01-26 16:00');
        $this->actingAs($this->hr);
        $period = ['center' => $this->plant->id, 'from' => '2027-01-25', 'to' => '2027-02-01'];

        $this->get(authorityReportUrl($period))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('people.data.0.days.0.flags', ['En curso'])
            ->where('summary.flaggedPeople', 0)->where('summary.withoutEndDays', 0)->where('summary.people', 2));
        $this->get(authorityReportUrl([...$period, 'show' => 'flagged']))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('people.total', 0)->where('summary.flaggedPeople', 0));
    });

    it('still counts a person with «En curso» and a real warning on other days', function (): void {
        pageDay($this->ana, '2027-02-01 08:00', null);
        pageDay($this->ana, '2027-01-28 09:00', null);
        pageDay($this->bob, '2027-02-01 09:00', null);
        $this->actingAs($this->hr);
        $period = ['center' => $this->plant->id, 'from' => '2027-01-25', 'to' => '2027-02-01'];

        $this->get(authorityReportUrl([...$period, 'show' => 'flagged']))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('people.total', 1)->where('people.data.0.name', 'Ana López')
            ->where('summary.flaggedPeople', 1)->where('summary.withoutEndDays', 1)->where('summary.people', 2));
    });

    it('applies the search after the filter', function (): void {
        pageDay($this->ana, '2027-01-26 08:00', '2027-01-26 16:00');
        pageDay($this->bob, '2027-01-28 09:00', null);
        $this->actingAs($this->hr);

        $this->get(authorityReportUrl(['center' => $this->plant->id, 'show' => 'flagged', 'q' => 'ana']))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('people.total', 0)->where('summary.people', 2));
    });

    it('refuses a filter it does not know', function (): void {
        $this->actingAs($this->hr);

        $this->get(authorityReportUrl(['center' => $this->plant->id, 'show' => 'todo']))
            ->assertRedirect(authorityReportUrl(['center' => $this->plant->id]))->assertSessionHasErrors(['show']);
    });
});

describe('who may see it', function (): void {
    it('refuses a viewer', function (): void {
        $this->actingAs(memberOf($this->tenant, TenantRole::Viewer));

        $this->get(authorityReportUrl())->assertForbidden();
        $this->get(authorityReportUrl(['center' => $this->plant->id]))->assertForbidden();
    });

    it('answers 404 to a union', function (): void {
        $union = Tenant::factory()->union()->create();
        $this->actingAs(memberOf($union, TenantRole::Owner));

        $this->get(authorityReportUrl([], $union->slug))->assertNotFound();
    });

    it('answers 404 to a center archived, of another company or unknown', function (): void {
        $archived = WorkCenter::factory()->for($this->tenant)->archived()->create();
        $foreign = WorkCenter::factory()->for(Tenant::factory()->create())->create();
        $this->actingAs($this->hr);

        $this->get(authorityReportUrl(['center' => $archived->id]))->assertNotFound();
        $this->get(authorityReportUrl(['center' => $foreign->id]))->assertNotFound();
        $this->get(authorityReportUrl(['center' => 'nope']))->assertNotFound();
    });
});

describe('the period', function (): void {
    it('shows up to 93 days on screen (the CSV takes up to 366)', function (): void {
        $this->actingAs($this->hr);

        $this->get(authorityReportUrl(['center' => $this->plant->id, 'from' => '2026-11-01', 'to' => '2027-02-01']))->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('period', ['from' => '2026-11-01', 'to' => '2027-02-01'])
                ->where('longPeriod', null)->whereNot('summary', null)->whereNot('people', null));
    });

    it('offers only the CSV for a period of 94 to 366 days: no table, no figures, and no view on the trail', function (string $from, string $to, int $days): void {
        pageDay($this->ana, '2027-01-26 08:00', '2027-01-26 18:00');
        $this->actingAs($this->hr);

        $this->get(authorityReportUrl(['center' => $this->plant->id, 'from' => $from, 'to' => $to]))->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('center', ['id' => $this->plant->id, 'name' => 'Planta Norte'])
                ->where('period', ['from' => $from, 'to' => $to])
                ->where('longPeriod', ['days' => $days, 'maxOnScreen' => 93])
                ->where('summary', null)
                ->where('people', null)
                ->where('generatedAt', null)
                ->where('exportUrl', route('tenant.attendance.report.export', ['acme', 'center' => $this->plant->id, 'from' => $from, 'to' => $to])));

        expect(App\Models\AuditEntry::query()->where('event', 'attendance.report_exported')->count())->toBe(0);
    })->with([
        '94 days' => ['2026-10-31', '2027-02-01', 94],
        '366 days' => ['2026-02-01', '2027-02-01', 366],
    ]);

    it('sends a wrong period back to the center\'s report with the reason in Spanish', function (array $query, array $errors): void {
        $this->actingAs($this->hr);

        $this->get(authorityReportUrl(['center' => $this->plant->id, ...$query]))
            ->assertRedirect(authorityReportUrl(['center' => $this->plant->id]))
            ->assertSessionHasErrors($errors);
    })->with([
        '367 days' => [['from' => '2026-01-31', 'to' => '2027-02-01'], ['to' => 'El periodo puede ser de hasta 366 días.']],
        'the end before the start' => [['from' => '2027-01-31', 'to' => '2027-01-25'], ['to' => 'La fecha final no puede ser anterior a la inicial.']],
        'only one date' => [['from' => '2027-01-25'], ['to' => 'El campo fecha final es obligatorio cuando fecha inicial esté presente.']],
        'not a date' => [['from' => 'ayer', 'to' => '2027-01-31'], ['from' => 'Escribe una fecha inicial válida.']],
    ]);
});

it('takes how many days it shows on screen from config/attendance.php (93 by default)', function (): void {
    expect(config('attendance.report.page_max_days'))->toBe(93);
    config(['attendance.report.page_max_days' => 7]);
    $this->actingAs($this->hr);

    $this->get(authorityReportUrl(['center' => $this->plant->id, 'from' => '2027-01-25', 'to' => '2027-01-31']))->assertInertia(fn (AssertableInertia $page) => $page->where('longPeriod', null));
    $this->get(authorityReportUrl(['center' => $this->plant->id, 'from' => '2027-01-24', 'to' => '2027-01-31']))->assertInertia(fn (AssertableInertia $page) => $page->where('longPeriod', ['days' => 8, 'maxOnScreen' => 7]));
});
