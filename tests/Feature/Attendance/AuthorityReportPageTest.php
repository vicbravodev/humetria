<?php

declare(strict_types=1);

use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Http\Controllers\ReportController;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\WeeklyConfirmation;
use App\Modules\Attendance\Support\ReportPages;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Inertia\Testing\AssertableInertia;

mutates(ReportController::class, ReportPages::class);

/** The queries of the report's page with a center, whatever the number of people or days: what was corrected and its view to the audit trail included. */
const REPORT_PAGE_QUERIES = 25;

beforeEach(function (): void {
    // Monday 1 February 2027, 12:00 in Mexico City: the last week that ended is 25 to 31 January.
    $this->travelTo(CarbonImmutable::parse('2027-02-01 18:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'name' => 'Ana López', 'job_title' => 'Operadora']);
    $this->bob = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'name' => 'Bob Díaz', 'job_title' => 'Almacenista']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
});

/** A day of `$person` from a start to an end, local times of Mexico City, marked at the plant's door. */
function pageDay(Employee $person, string $start, ?string $end): AttendanceMark
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

describe('the page', function (): void {
    it('asks for a center first, with the last week that ended as the period', function (): void {
        $this->actingAs($this->hr);

        $this->get(authorityReportUrl())->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('attendance/report')
            ->where('center', null)
            ->where('period', ['from' => '2027-01-25', 'to' => '2027-01-31'])
            ->where('filters', ['q' => ''])
            ->where('summary', null)
            ->where('people', null)
            ->where('exportUrl', null));
    });

    it('shows the center\'s period: its figures, the people page by page with their days, and the link to the CSV', function (): void {
        pageDay($this->ana, '2027-01-26 08:00', '2027-01-26 18:00');
        $corrected = pageDay($this->ana, '2027-01-27 08:20', '2027-01-27 16:00');
        AttendanceMark::factory()->correcting($corrected)->create(['marked_at' => CarbonImmutable::parse('2027-01-27 08:00', 'America/Mexico_City')->utc(), 'reason' => 'El lector falló.']);
        WeeklyConfirmation::factory()->confirmed()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'week_start' => '2027-01-25']);
        pageDay($this->bob, '2027-01-28 09:00', null);
        $this->actingAs($this->hr);

        $response = $this->get(authorityReportUrl(['center' => $this->plant->id, 'from' => '2027-01-25', 'to' => '2027-01-31']))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('center', ['id' => $this->plant->id, 'name' => 'Planta Norte'])
            ->where('period', ['from' => '2027-01-25', 'to' => '2027-01-31'])
            ->where('summary', ['people' => 2, 'days' => 3, 'workedMinutes' => 1080, 'confirmedPercent' => 67, 'withCorrections' => 1, 'unconfirmed' => 1])
            ->where('people.total', 2)
            ->where('people.per_page', 25)
            ->where('people.data.0.name', 'Ana López')
            ->where('people.data.0.jobTitle', 'Operadora')
            ->where('people.data.0.days.0', [
                'date' => '2027-01-26', 'start' => '08:00', 'end' => '18:00', 'endDate' => '2027-01-26', 'breakMinutes' => 0, 'workedMinutes' => 600,
                'ordinaryMinutes' => 480, 'doubleMinutes' => 120, 'tripleMinutes' => 0, 'restDayMinutes' => 0, 'evidence' => 'Fuerte',
                'corrections' => [], 'confirmation' => 'confirmado', 'flags' => [],
            ])
            ->where('people.data.0.days.1.corrections', ['hora corregida de 08:20 a 08:00 (por RH): El lector falló.'])
            ->where('people.data.1.days.0.flags', ['Sin marca de fin'])
            ->where('generatedAt', ['utc' => '2027-02-01 18:00', 'local' => '2027-02-01 12:00'])
            ->where('exportUrl', route('tenant.attendance.report.export', ['acme', 'center' => $this->plant->id, 'from' => '2027-01-25', 'to' => '2027-01-31'])));

        expect($response->getContent())->not->toContain('19.4327')->not->toContain('latitude')->not->toContain('longitude')
            ->not->toContain('accuracy')->not->toContain('distance')->not->toContain('device_id')->not->toContain('deviceId');
    });

    it('shows an empty period as such, with no people', function (): void {
        $this->actingAs($this->hr);

        $this->get(authorityReportUrl(['center' => $this->plant->id]))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('summary', ['people' => 0, 'days' => 0, 'workedMinutes' => 0, 'confirmedPercent' => 0, 'withCorrections' => 0, 'unconfirmed' => 0])
            ->where('people.total', 0));
    });

    it('pages the people 25 at a time and searches them by name, the figures of all of them', function (): void {
        foreach (EmployeeFactory::new()->count(25)->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]) as $person) {
            pageDay($person, '2027-01-26 08:00', '2027-01-26 16:00');
        }
        pageDay($this->bob, '2027-01-26 08:00', '2027-01-26 16:00');
        $this->actingAs($this->hr);

        $this->get(authorityReportUrl(['center' => $this->plant->id, 'page' => 2]))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('people.total', 26)->has('people.data', 1)->where('people.current_page', 2));
        $this->get(authorityReportUrl(['center' => $this->plant->id, 'q' => 'DIAZ']))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filters', ['q' => 'DIAZ'])
            ->where('people.total', 1)
            ->where('people.data.0.name', 'Bob Díaz')
            ->where('summary.people', 26));
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
    it('takes up to 366 days', function (): void {
        $this->actingAs($this->hr);

        $this->get(authorityReportUrl(['center' => $this->plant->id, 'from' => '2026-01-01', 'to' => '2027-01-01']))->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('period', ['from' => '2026-01-01', 'to' => '2027-01-01']));
    });

    it('sends a wrong period back to the center\'s report with the reason in Spanish', function (array $query, array $errors): void {
        $this->actingAs($this->hr);

        $this->get(authorityReportUrl(['center' => $this->plant->id, ...$query]))
            ->assertRedirect(authorityReportUrl(['center' => $this->plant->id]))
            ->assertSessionHasErrors($errors);
    })->with([
        '367 days' => [['from' => '2026-01-01', 'to' => '2027-01-02'], ['to' => 'El periodo puede ser de hasta 366 días.']],
        'the end before the start' => [['from' => '2027-01-31', 'to' => '2027-01-25'], ['to' => 'La fecha final no puede ser anterior a la inicial.']],
        'only one date' => [['from' => '2027-01-25'], ['to' => 'El campo fecha final es obligatorio cuando fecha inicial esté presente.']],
        'not a date' => [['from' => 'ayer', 'to' => '2027-01-31'], ['from' => 'Escribe una fecha inicial válida.']],
    ]);
});
