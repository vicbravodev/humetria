<?php

declare(strict_types=1);

use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Http\Controllers\AttendanceController;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\AttendanceSchedule;
use App\Modules\Attendance\Models\AttendanceZone;
use App\Modules\Attendance\Models\CorrectionRequest;
use App\Modules\Attendance\Models\KioskDevice;
use App\Modules\Attendance\Models\WeeklyConfirmation;
use App\Modules\Attendance\Support\AttendancePages;
use App\Modules\Attendance\Support\PanelScope;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Inertia\Testing\AssertableInertia;

mutates(AttendanceController::class, AttendancePages::class, PanelScope::class);

/** The queries of the overview, whatever the number of people, centers or marks (34: the page's schedules, for «Sin horario», and the road to the first mark). */
const OVERVIEW_QUERIES = 34;

beforeEach(function (): void {
    // Wednesday 11 November 2026, 12:00 in Mexico City: the last week that ended is 2 to 8 November.
    $this->travelTo(CarbonImmutable::parse('2026-11-11 18:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    $this->office = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Oficinas']);
    AttendanceZone::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'latitude' => 19.432712, 'longitude' => -99.133298]);
    $this->ana = overviewPerson($this->plant, 'Ana López', 'Operadora');
    $this->bob = overviewPerson($this->plant, 'Bob Díaz', 'Almacenista');
    $this->carla = overviewPerson($this->office, 'Carla Ruiz', 'Analista');
    $this->dan = overviewPerson($this->office, 'Dan Soto', 'Contador');
    EmployeeFactory::new()->deactivated()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'name' => 'Ex Empleado']);
    $closed = WorkCenter::factory()->for($this->tenant)->archived()->create();
    EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $closed->id, 'name' => 'Persona Archivada']);
    // Ana: Tuesday from 8 to 18, two hours of overtime; she confirmed. Bob disputed. Carla worked 13 h on Tuesday. Dan nothing.
    overviewDay($this->ana, '2026-11-03 08:00', '2026-11-03 18:00');
    WeeklyConfirmation::factory()->confirmed()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'week_start' => '2026-11-02']);
    WeeklyConfirmation::factory()->disputed()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->bob->id, 'week_start' => '2026-11-02']);
    overviewDay($this->carla, '2026-11-03 07:00', '2026-11-03 20:00');
    WeeklyConfirmation::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->dan->id, 'week_start' => '2026-11-02']);
    CorrectionRequest::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->dan->id]);
    // Only Dan has an agreed schedule.
    AttendanceSchedule::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->dan->id]);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
});

function overviewPerson(WorkCenter $center, string $name, string $jobTitle): Employee
{
    return EmployeeFactory::new()->create(['tenant_id' => $center->tenant_id, 'work_center_id' => $center->id, 'name' => $name, 'job_title' => $jobTitle]);
}

/** A day of `$person` from a start to an end, local times of Mexico City, marked in a zone. */
function overviewDay(Employee $person, string $start, string $end): void
{
    foreach ([[MarkType::Start, $start], [MarkType::End, $end]] as [$type, $local]) {
        AttendanceMark::factory()->create([
            'tenant_id' => $person->tenant_id, 'employee_id' => $person->id, 'work_center_id' => $person->work_center_id, 'type' => $type,
            'marked_at' => CarbonImmutable::parse($local, 'America/Mexico_City')->utc(), 'latitude' => '19.432712', 'longitude' => '-99.133298', 'accuracy_m' => 12.5, 'distance_m' => 11.25,
        ]);
    }
}

function overviewUrl(array $query = []): string
{
    return route('tenant.attendance.index', ['acme', ...$query]);
}

describe('the summary', function (): void {
    it('shows RH the last week that ended, by state, with the people page by page', function (): void {
        $this->actingAs($this->hr);

        $response = $this->get(overviewUrl())->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('attendance/index')
            ->where('week', ['start' => '2026-11-02', 'end' => '2026-11-08', 'over' => true, 'previous' => '2026-10-26', 'next' => '2026-11-09'])
            ->where('center', null)
            ->where('filters', ['q' => '', 'status' => 'all'])
            ->where('counts', ['all' => 4, 'pending' => 2, 'disputed' => 1, 'confirmed' => 1, 'at_risk' => 1])
            ->where('pendingCorrections', 1)
            ->where('centersWithoutZones', 1)
            ->where('canManage', true)
            ->where('people.total', 4)
            ->where('people.per_page', 25)
            ->where('people.data.0', [
                'id' => $this->ana->id, 'name' => 'Ana López', 'jobTitle' => 'Operadora', 'center' => 'Planta Norte',
                'ordinaryMinutes' => 480, 'overtimeMinutes' => 120, 'flags' => [], 'status' => 'confirmed', 'atRisk' => false, 'hasSchedule' => false,
                'days' => [['worked' => 0, 'overtime' => 0], ['worked' => 600, 'overtime' => 120], ...array_fill(0, 5, ['worked' => 0, 'overtime' => 0])],
            ])
            ->where('people.data.1.status', 'disputed')
            ->where('people.data.2', [
                'id' => $this->carla->id, 'name' => 'Carla Ruiz', 'jobTitle' => 'Analista', 'center' => 'Oficinas',
                'ordinaryMinutes' => 480, 'overtimeMinutes' => 300, 'flags' => ['day_over_12_hours', 'daily_overtime_over_4'], 'status' => 'pending', 'atRisk' => true, 'hasSchedule' => false,
                'days' => [['worked' => 0, 'overtime' => 0], ['worked' => 780, 'overtime' => 300], ...array_fill(0, 5, ['worked' => 0, 'overtime' => 0])],
            ])
            // Dan has no marks and a week still pending.
            ->where('people.data.3', [
                'id' => $this->dan->id, 'name' => 'Dan Soto', 'jobTitle' => 'Contador', 'center' => 'Oficinas',
                'ordinaryMinutes' => 0, 'overtimeMinutes' => 0, 'flags' => [], 'status' => 'pending', 'atRisk' => false, 'hasSchedule' => true,
                'days' => array_fill(0, 7, ['worked' => 0, 'overtime' => 0]),
            ])
            ->where('centers.total', 2)
            ->where('centers.data.0', ['id' => $this->office->id, 'name' => 'Oficinas', 'people' => 2, 'zones' => 0, 'screens' => 0])
            ->where('centers.data.1', ['id' => $this->plant->id, 'name' => 'Planta Norte', 'people' => 2, 'zones' => 1, 'screens' => 0]));

        expect($response->getContent())->not->toContain('19.4327')->not->toContain('latitude')->not->toContain('longitude')
            ->not->toContain('accuracy')->not->toContain('distance')->not->toContain('device_id')->not->toContain('deviceId');
    });

    it('gives each person the minutes of each day and of it the overtime, Monday to Sunday, for the week ribbon', function (): void {
        $this->actingAs($this->hr);

        $this->get(overviewUrl())->assertInertia(fn (AssertableInertia $page) => $page
            ->where('people.data.0.days', [
                ['worked' => 0, 'overtime' => 0], ['worked' => 600, 'overtime' => 120], ['worked' => 0, 'overtime' => 0], ['worked' => 0, 'overtime' => 0],
                ['worked' => 0, 'overtime' => 0], ['worked' => 0, 'overtime' => 0], ['worked' => 0, 'overtime' => 0],
            ])
            ->where('people.data.2.days.1', ['worked' => 780, 'overtime' => 300])
            ->where('people.data.3.days', array_fill(0, 7, ['worked' => 0, 'overtime' => 0])));
    });

    it('adds up the hours of everyone in scope, not only of the page shown, and of one center when narrowed', function (): void {
        $this->actingAs($this->hr);

        $this->get(overviewUrl())->assertInertia(fn (AssertableInertia $page) => $page
            ->where('hours', ['ordinaryMinutes' => 960, 'overtimeMinutes' => 420]));
        $this->get(overviewUrl(['center' => $this->office->id, 'q' => 'zzz']))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('people.total', 0)
            ->where('hours', ['ordinaryMinutes' => 480, 'overtimeMinutes' => 300]));
    });

    it('lists each state on its own tab, and the weeks at risk', function (string $status, array $names): void {
        $this->actingAs($this->hr);

        $this->get(overviewUrl(['status' => $status]))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filters.status', $status === 'everything' ? 'all' : $status)
            ->where('people.data', fn ($people): bool => collect($people)->pluck('name')->all() === $names));
    })->with([
        'pending, with no answer or a pending one' => ['pending', ['Carla Ruiz', 'Dan Soto']],
        'disputed' => ['disputed', ['Bob Díaz']],
        'confirmed' => ['confirmed', ['Ana López']],
        'at risk' => ['at_risk', ['Carla Ruiz']],
        'unknown, all of them' => ['everything', ['Ana López', 'Bob Díaz', 'Carla Ruiz', 'Dan Soto']],
    ]);

    it('counts a screen that shows the code, not one revoked nor one opened but never seen, as the center page does', function (): void {
        KioskDevice::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'activated_at' => now(), 'last_seen_at' => now()]);
        KioskDevice::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'activated_at' => now(), 'last_seen_at' => now(), 'revoked_at' => now()]);
        // A chat's link preview opened its link: activated, never seen.
        KioskDevice::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'activated_at' => now()]);
        $this->actingAs($this->hr);

        $this->get(overviewUrl())->assertInertia(fn (AssertableInertia $page) => $page->where('centers.data.1.screens', 1));
    });

    it('narrows everything to one center', function (): void {
        $this->actingAs($this->hr);

        $this->get(overviewUrl(['center' => $this->office->id]))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('center', ['id' => $this->office->id, 'name' => 'Oficinas'])
            ->where('counts', ['all' => 2, 'pending' => 2, 'disputed' => 0, 'confirmed' => 0, 'at_risk' => 1])
            ->where('centersWithoutZones', 1)
            ->where('people.total', 2));
    });

    it('answers 404 to a center archived, of another company or unknown', function (): void {
        $archived = WorkCenter::factory()->for($this->tenant)->archived()->create();
        $foreign = WorkCenter::factory()->for(Tenant::factory()->create())->create();
        $this->actingAs($this->hr);

        $this->get(overviewUrl(['center' => $archived->id]))->assertNotFound();
        $this->get(overviewUrl(['center' => $foreign->id]))->assertNotFound();
        $this->get(overviewUrl(['center' => 'nope']))->assertNotFound();
    });

    it('searches the people by name, without case or accents', function (): void {
        $this->actingAs($this->hr);

        $this->get(overviewUrl(['q' => 'DIAZ']))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filters', ['q' => 'DIAZ', 'status' => 'all'])
            ->where('people.total', 1)
            ->where('people.data.0.name', 'Bob Díaz')
            // The tabs keep counting the whole week.
            ->where('counts.all', 4));
    });

    it('pages the people 25 at a time', function (): void {
        EmployeeFactory::new()->count(22)->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
        $this->actingAs($this->hr);

        $this->get(overviewUrl(['page' => 2]))->assertInertia(fn (AssertableInertia $page) => $page->where('people.total', 26)->has('people.data', 1)->where('people.current_page', 2));
    });

    it('reads the overview in the same number of queries, however many people and marks', function (int $more): void {
        foreach (EmployeeFactory::new()->count($more)->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->office->id]) as $person) {
            overviewDay($person, '2026-11-04 08:00', '2026-11-04 17:00');
        }
        $this->actingAs($this->hr);

        $this->expectsDatabaseQueryCount(OVERVIEW_QUERIES);
        $this->get(overviewUrl())->assertOk();
    })->with(['a few' => [0], 'many more' => [30]]);
});

describe('the week', function (): void {
    it('takes the week of any of its days, Monday to Sunday', function (string $day, string $start): void {
        $this->actingAs($this->hr);

        $this->get(overviewUrl(['week' => $day]))->assertInertia(fn (AssertableInertia $page) => $page->where('week.start', $start));
    })->with([
        'a Sunday, the last day of its week' => ['2026-11-08', '2026-11-02'],
        'a Monday, the first of the next' => ['2026-10-26', '2026-10-26'],
        'not a date, the last week that ended' => ['yesterday', '2026-11-02'],
    ]);

    it('shows the week in course, with no next one, and never a week to come', function (string $day): void {
        $this->actingAs($this->hr);

        $this->get(overviewUrl(['week' => $day]))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('week', ['start' => '2026-11-09', 'end' => '2026-11-15', 'over' => false, 'previous' => '2026-11-02', 'next' => null])
            ->where('counts.pending', 4));
    })->with(['this week' => ['2026-11-09'], 'a week to come' => ['2026-12-07']]);
});

describe('who sees it', function (): void {
    it('lets a viewer read it, without changing anything', function (): void {
        $this->actingAs(memberOf($this->tenant, TenantRole::Viewer));

        $this->get(overviewUrl())->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('canManage', false)->where('people.total', 4));
    });

    it('keeps the committee out', function (): void {
        $this->actingAs(memberOf($this->tenant, TenantRole::Committee));

        $this->get(overviewUrl())->assertForbidden();
    });

    it('answers 404 to a union account', function (): void {
        $union = Tenant::factory()->union()->create();
        $this->actingAs(memberOf($union, TenantRole::Owner));

        $this->get(route('tenant.attendance.index', $union->slug))->assertNotFound();
    });

    it('never counts or lists the people of another company', function (): void {
        $globex = Tenant::factory()->create();
        $theirs = WorkCenter::factory()->for($globex)->create();
        $outsider = EmployeeFactory::new()->create(['tenant_id' => $globex->id, 'work_center_id' => $theirs->id]);
        WeeklyConfirmation::factory()->confirmed()->create(['tenant_id' => $globex->id, 'employee_id' => $outsider->id, 'week_start' => '2026-11-02']);
        workIn($this->tenant);
        $this->actingAs($this->hr);

        $this->get(overviewUrl())->assertInertia(fn (AssertableInertia $page) => $page->where('counts.all', 4)->where('counts.confirmed', 1)->where('centers.total', 2));
    });
});

it('shows an empty company with nothing to count', function (): void {
    $empty = Tenant::factory()->create(['slug' => 'nueva']);
    $this->actingAs(memberOf($empty, TenantRole::Owner));

    $this->get(route('tenant.attendance.index', 'nueva'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('counts', ['all' => 0, 'pending' => 0, 'disputed' => 0, 'confirmed' => 0, 'at_risk' => 0])
        ->where('pendingCorrections', 0)
        ->where('centersWithoutZones', 0)
        ->where('people.total', 0)
        ->where('centers.total', 0));
});
