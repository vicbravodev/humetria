<?php

declare(strict_types=1);

use App\Modules\Attendance\Models\AttendanceDevice;
use App\Modules\Attendance\Models\AttendancePolicy;
use App\Modules\Attendance\Models\AttendanceSchedule;
use App\Modules\Attendance\Models\AttendanceZone;
use App\Modules\Attendance\Models\KioskDevice;
use App\Modules\Attendance\Support\SetupProgress;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Inertia\Testing\AssertableInertia;

mutates(SetupProgress::class);

/** The queries of the setup page, whatever the number of centers or people. */
const SETUP_QUERIES = 21;

beforeEach(function (): void {
    // 8 November 2026 in Mexico City: 54 days before 1 January 2027.
    $this->travelTo(CarbonImmutable::parse('2026-11-08 18:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    $this->office = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Oficinas']);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'name' => 'Ana López']);
    $this->bob = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'name' => 'Bob Díaz']);
    $this->elena = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->office->id, 'name' => 'Elena Vega']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
});

function setupProps(): array
{
    return test()->actingAs(test()->hr)->get(route('tenant.attendance.setup', 'acme'))->assertOk()->inertiaProps('setup');
}

describe('the road to the first mark', function (): void {
    it('counts down to 1 January 2027, the day the registro becomes mandatory', function (): void {
        expect(setupProps())->toMatchArray(['deadline' => '2027-01-01', 'daysLeft' => 54]);
    });

    it('does not count negative days once the date has passed', function (): void {
        $this->travelTo(CarbonImmutable::parse('2027-02-01 18:00:00', 'UTC'));

        expect(setupProps())->toMatchArray(['deadline' => '2027-01-01', 'daysLeft' => 0]);
    });

    it('counts a day left until its midnight in Mexico City, not in UTC', function (): void {
        // 31 December 2026, 20:00 in Mexico City is already 1 January in UTC: one day is still left.
        $this->travelTo(CarbonImmutable::parse('2027-01-01 02:00:00', 'UTC'));

        expect(setupProps())->toMatchArray(['daysLeft' => 1]);
    });

    it('starts with nothing done: no center can take a mark, nobody has a schedule or a phone, the policy is the default', function (): void {
        $props = setupProps();

        expect($props['centers'])->toMatchArray(['done' => 0, 'total' => 2])
            ->and($props['schedules'])->toMatchArray(['done' => 0, 'total' => 3])
            ->and($props['devices'])->toMatchArray(['done' => 0, 'total' => 3])
            ->and($props['policy'])->toBe(['done' => 0, 'total' => 1])
            ->and($props['requiresQr'])->toBeTrue();
    });

    it('says what each pending center lacks: a zone, a screen, or both', function (): void {
        AttendanceZone::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);

        expect(setupProps()['centers']['pending'])->toBe([
            ['id' => $this->office->id, 'name' => 'Oficinas', 'needs' => ['zone', 'screen']],
            ['id' => $this->plant->id, 'name' => 'Planta Norte', 'needs' => ['screen']],
        ]);
    });

    it('is ready with a zone and a screen that shows the code, not one revoked nor one opened and never seen', function (): void {
        AttendanceZone::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
        KioskDevice::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'activated_at' => now(), 'last_seen_at' => now(), 'revoked_at' => now()]);
        KioskDevice::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'activated_at' => now()]);

        expect(setupProps()['centers']['done'])->toBe(0);

        KioskDevice::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'activated_at' => now(), 'last_seen_at' => now()]);

        expect(setupProps()['centers'])->toMatchArray(['done' => 1, 'total' => 2]);
    });

    it('asks no screen of a center when the policy does not ask for the QR on site', function (): void {
        AttendancePolicy::factory()->create(['tenant_id' => $this->tenant->id, 'require_qr_onsite' => false]);
        AttendanceZone::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);

        $props = setupProps();

        expect($props['requiresQr'])->toBeFalse()
            ->and($props['centers']['done'])->toBe(1)
            ->and($props['centers']['pending'])->toBe([['id' => $this->office->id, 'name' => 'Oficinas', 'needs' => ['zone']]])
            // A saved policy is a reviewed policy.
            ->and($props['policy'])->toBe(['done' => 1, 'total' => 1]);
    });

    it('counts the people with a schedule, naming a few of those without', function (): void {
        AttendanceSchedule::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);

        $schedules = setupProps()['schedules'];

        expect($schedules)->toMatchArray(['done' => 1, 'total' => 3])
            ->and($schedules['pending'])->toBe(['Bob Díaz', 'Elena Vega']);
    });

    it('names at most three people without a schedule', function (): void {
        foreach (['Carla Ruiz', 'Dan Soto'] as $name) {
            EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'name' => $name]);
        }

        $schedules = setupProps()['schedules'];

        expect($schedules)->toMatchArray(['done' => 0, 'total' => 5])
            ->and($schedules['pending'])->toHaveCount(3);
    });

    it('counts the people with an active device, not one with only a revoked one', function (): void {
        AttendanceDevice::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
        AttendanceDevice::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->bob->id, 'revoked_at' => now()]);

        expect(setupProps()['devices'])->toMatchArray(['done' => 1, 'total' => 3]);
    });

    it('leaves out archived centers and people no longer active', function (): void {
        $closed = WorkCenter::factory()->for($this->tenant)->archived()->create();
        EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $closed->id]);
        EmployeeFactory::new()->deactivated()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);

        $props = setupProps();

        expect($props['centers']['total'])->toBe(2)
            ->and($props['schedules']['total'])->toBe(3)
            ->and($props['devices']['total'])->toBe(3);
    });

    it('warns of a zone smaller than the accuracy the policy accepts', function (): void {
        AttendanceZone::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'name' => 'Nave 1', 'radius_m' => 80]);
        AttendanceZone::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->office->id, 'name' => 'Recepción', 'radius_m' => 100]);

        expect(setupProps()['findings'])->toBe([
            ['center' => ['id' => $this->plant->id, 'name' => 'Planta Norte'], 'zone' => 'Nave 1', 'radius' => 80, 'tolerance' => 100],
        ]);
    });

    it('warns once per center, about its smallest zone', function (): void {
        AttendanceZone::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'name' => 'Patio', 'radius_m' => 90]);
        AttendanceZone::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'name' => 'Nave 1', 'radius_m' => 40]);

        expect(setupProps()['findings'])->toHaveCount(1)->and(setupProps()['findings'][0])->toMatchArray(['zone' => 'Nave 1', 'radius' => 40]);
    });

    it('does not mix in the centers, people or screens of another company', function (): void {
        $globex = Tenant::factory()->create();
        $theirs = WorkCenter::factory()->for($globex)->create();
        $outsider = EmployeeFactory::new()->create(['tenant_id' => $globex->id, 'work_center_id' => $theirs->id]);
        AttendanceZone::factory()->create(['tenant_id' => $globex->id, 'work_center_id' => $theirs->id]);
        KioskDevice::factory()->create(['tenant_id' => $globex->id, 'work_center_id' => $theirs->id, 'activated_at' => now(), 'last_seen_at' => now()]);
        AttendanceDevice::factory()->create(['tenant_id' => $globex->id, 'employee_id' => $outsider->id]);
        AttendanceSchedule::factory()->create(['tenant_id' => $globex->id, 'employee_id' => $outsider->id]);
        workIn($this->tenant);

        $props = setupProps();

        expect($props['centers'])->toMatchArray(['done' => 0, 'total' => 2])
            ->and($props['schedules']['done'])->toBe(0)
            ->and($props['devices']['done'])->toBe(0)
            ->and($props['findings'])->toBe([]);
    });

    it('is read in the same number of queries, however many centers and people', function (int $more): void {
        $center = WorkCenter::factory()->for($this->tenant)->create();
        foreach (EmployeeFactory::new()->count($more)->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $center->id]) as $person) {
            AttendanceDevice::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $person->id]);
        }
        $this->actingAs($this->hr);

        $this->expectsDatabaseQueryCount(SETUP_QUERIES);
        $this->get(route('tenant.attendance.setup', 'acme'))->assertOk();
    })->with(['a few' => [1], 'many more' => [20]]);
});

describe('the page', function (): void {
    it('renders the road for RH, who may read it but a viewer cannot change anything', function (): void {
        $this->actingAs(memberOf($this->tenant, TenantRole::Viewer));

        $this->get(route('tenant.attendance.setup', 'acme'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('attendance/setup')
            ->where('canManage', false)
            ->has('setup.centers'));
    });

    it('keeps the committee out', function (): void {
        $this->actingAs(memberOf($this->tenant, TenantRole::Committee));

        $this->get(route('tenant.attendance.setup', 'acme'))->assertForbidden();
    });

    it('answers 404 to a union account', function (): void {
        $union = Tenant::factory()->union()->create();
        $this->actingAs(memberOf($union, TenantRole::Owner));

        $this->get(route('tenant.attendance.setup', $union->slug))->assertNotFound();
    });

    it('sends no position, device or email', function (): void {
        AttendanceZone::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'latitude' => 19.432712, 'longitude' => -99.133298, 'radius_m' => 50]);
        AttendanceDevice::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
        $this->actingAs($this->hr);

        $setup = json_encode($this->get(route('tenant.attendance.setup', 'acme'))->inertiaProps('setup'));

        expect($setup)->not->toContain('19.4327')->not->toContain('latitude')->not->toContain('credential')->not->toContain('email')->not->toContain('@');
    });
});
