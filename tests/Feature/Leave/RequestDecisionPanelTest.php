<?php

declare(strict_types=1);

use App\Modules\Leave\Actions\DecideRequest;
use App\Modules\Leave\Http\Controllers\RequestController;
use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveEntry;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Leave\Support\Approval;
use App\Modules\Leave\Support\Decider;
use App\Modules\Leave\Support\DecisionContext;
use App\Modules\Leave\Support\RequestPages;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;

mutates(RequestController::class, RequestPages::class, DecisionContext::class, Approval::class);

beforeEach(function (): void {
    workIn($this->tenant = Tenant::factory()->create(['slug' => 'acme']));
    $this->travelTo('2026-11-04 18:00:00');
    LeaveSetting::factory()->create(['tenant_id' => $this->tenant->id, 'tracking_since' => '2025-01-01']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->boss = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Beto Ruiz']);
    $this->ana = Employee::factory()->reportingTo($this->boss)->create(['name' => 'Ana Solís']);
    $this->caro = Employee::factory()->reportingTo($this->boss)->create(['name' => 'Caro Luz']);
    $this->dan = Employee::factory()->reportingTo($this->boss)->create(['name' => 'Dan Gil']);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function decisionPanelRequest(Employee $person, string $state = 'escalated', array $attributes = []): LeaveRequest
{
    $factory = LeaveRequest::factory();
    $factory = match ($state) {
        'approved' => $factory->approved(),
        'taken' => $factory->taken(),
        'escalated' => $factory->escalated(),
        default => $factory,
    };

    return $factory->create(['tenant_id' => $person->tenant_id, 'employee_id' => $person->id, 'approver_employee_id' => $person->manager_id, 'starts_on' => '2026-11-18', 'ends_on' => '2026-11-20', 'working_days' => 3, ...$attributes]);
}

/**
 * @param  array<string, mixed>  $query
 * @return array<string, array<string, mixed>>
 */
function decisionPanelRows(array $query = []): array
{
    $props = test()->actingAs(test()->hr)->get(route('tenant.vacations.requests.index', [test()->tenant, ...$query]))->assertOk()->inertiaProps();

    return array_column($props['requests']['data'], null, 'id');
}

function decisionPanelPeriod(Employee $person, int $days): LeaveEntitlement
{
    return LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $person->tenant_id, 'employee_id' => $person->id, 'years_of_service' => 1, 'anniversary_on' => '2026-03-15', 'statutory_days' => $days]);
}

describe('the days that would be left', function (): void {
    it('tells RH whether the days cover the request and how many would be left, from the periods', function (): void {
        decisionPanelPeriod($this->ana, 23);
        decisionPanelPeriod($this->caro, 2);
        $covered = decisionPanelRequest($this->ana);
        $short = decisionPanelRequest($this->caro);
        $none = decisionPanelRequest($this->dan);

        $rows = decisionPanelRows();

        expect($rows[$covered->id]['coverage'])->toBe(['fits' => true, 'leftAfter' => 20])
            ->and($rows[$short->id]['coverage'])->toBe(['fits' => false, 'leftAfter' => -1])
            ->and($rows[$none->id]['coverage'])->toBe(['fits' => false, 'leftAfter' => -3]);
    });

    it('is the very answer the approval gives: what it says fits is what approving charges to the periods', function (): void {
        $period = decisionPanelPeriod($this->ana, 23);
        $request = decisionPanelRequest($this->ana);
        $before = decisionPanelRows()[$request->id]['coverage'];

        resolve(DecideRequest::class)->handle($request->id, true, Decider::hr($this->hr->id));

        $left = (int) LeaveEntry::query()->where('leave_entitlement_id', $period->id)->sum('days');
        expect($before['leftAfter'])->toBe($left);
    });

    it('says nothing of the days for a leave, for what is not waiting, nor to a viewer', function (): void {
        decisionPanelPeriod($this->ana, 23);
        $type = LeaveType::factory()->create(['tenant_id' => $this->tenant->id]);
        $leave = LeaveRequest::factory()->ofType($type)->escalated()->create(['employee_id' => $this->ana->id, 'approver_employee_id' => $this->boss->id]);
        $approved = decisionPanelRequest($this->caro, 'approved');
        $waiting = decisionPanelRequest($this->dan);

        $rows = decisionPanelRows(['tab' => 'attend']) + decisionPanelRows(['tab' => 'approved']);

        expect($rows[$leave->id]['coverage'])->toBeNull()
            ->and($rows[$approved->id]['coverage'])->toBeNull();

        $viewer = memberOf($this->tenant, TenantRole::Viewer);
        $props = $this->actingAs($viewer)->get(route('tenant.vacations.requests.index', $this->tenant))->assertOk()->inertiaProps();
        $viewed = array_column($props['requests']['data'], null, 'id');
        expect($viewed[$waiting->id]['coverage'])->toBeNull();
    });
});

describe('who of the team is out those days', function (): void {
    it('counts, day by day, the teammates of the same manager who are away, and names them without saying why', function (): void {
        $request = decisionPanelRequest($this->ana);
        decisionPanelRequest($this->caro, 'approved', ['starts_on' => '2026-11-19', 'ends_on' => '2026-11-24', 'note' => 'Boda de mi hermana']);
        $type = LeaveType::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Maternidad']);
        LeaveRequest::factory()->ofType($type)->approved()->create(['employee_id' => $this->dan->id, 'approver_employee_id' => $this->boss->id, 'starts_on' => '2026-11-20', 'ends_on' => '2026-11-20', 'working_days' => 1]);

        $team = decisionPanelRows()[$request->id]['team'];

        expect($team)->toBe([
            'days' => [['date' => '2026-11-18', 'away' => 0], ['date' => '2026-11-19', 'away' => 1], ['date' => '2026-11-20', 'away' => 2]],
            'names' => ['Caro Luz', 'Dan Gil'],
        ])->and(json_encode($team))->not->toContain('Maternidad')->not->toContain('Boda');
    });

    it('ignores what is only asked for, rejected or cancelled, people of another manager and people who left', function (): void {
        $request = decisionPanelRequest($this->ana);
        decisionPanelRequest($this->caro, 'requested');
        $other = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Otro Jefe']);
        $stranger = Employee::factory()->reportingTo($other)->create(['name' => 'Ajena']);
        decisionPanelRequest($stranger, 'approved');
        $gone = Employee::factory()->reportingTo($this->boss)->deactivated()->create(['name' => 'Luis Ido']);
        decisionPanelRequest($gone, 'approved');

        expect(decisionPanelRows()[$request->id]['team'])->toBe([
            'days' => [['date' => '2026-11-18', 'away' => 0], ['date' => '2026-11-19', 'away' => 0], ['date' => '2026-11-20', 'away' => 0]],
            'names' => [],
        ]);
    });

    it('gives none for a person with no manager, for a request already answered, and caps the strip at fourteen days', function (): void {
        $solo = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Sola']);
        $lonely = decisionPanelRequest($solo, 'escalated', ['approver_employee_id' => null]);
        $long = decisionPanelRequest($this->ana, 'escalated', ['starts_on' => '2026-12-01', 'ends_on' => '2026-12-31', 'working_days' => 22]);
        $answered = decisionPanelRequest($this->caro, 'approved');

        $rows = decisionPanelRows() + decisionPanelRows(['tab' => 'approved']);

        expect($rows[$lonely->id]['team']['names'])->toBe([])
            ->and($rows[$long->id]['team']['days'])->toHaveCount(14)
            ->and($rows[$answered->id]['team'])->toBeNull();
    });

    it('never mixes another company', function (): void {
        $request = decisionPanelRequest($this->ana);
        $elsewhere = Tenant::factory()->create();
        $boss = Employee::factory()->create(['tenant_id' => $elsewhere->id]);
        $ghost = Employee::factory()->reportingTo($boss)->create(['name' => 'De otra empresa']);
        LeaveRequest::factory()->approved()->create(['tenant_id' => $elsewhere->id, 'employee_id' => $ghost->id, 'starts_on' => '2026-11-18', 'ends_on' => '2026-11-20']);

        expect(decisionPanelRows()[$request->id]['team']['names'])->toBe([]);
    });
});

it('lists what waits by the date it starts, the closest first', function (): void {
    $later = decisionPanelRequest($this->ana, 'escalated', ['starts_on' => '2026-12-21', 'ends_on' => '2026-12-22', 'requested_on' => '2026-11-01']);
    $sooner = decisionPanelRequest($this->caro, 'escalated', ['starts_on' => '2026-11-18', 'requested_on' => '2026-11-03']);

    $props = test()->actingAs($this->hr)->get(route('tenant.vacations.requests.index', $this->tenant))->assertOk()->inertiaProps();

    expect(array_column($props['requests']['data'], 'id'))->toBe([$sooner->id, $later->id]);
});

it('says since when each request waits, the day it was asked', function (): void {
    $request = decisionPanelRequest($this->ana, 'escalated', ['requested_on' => '2026-11-03']);

    expect(decisionPanelRows()[$request->id]['requestedOn'])->toBe('2026-11-03');
});
