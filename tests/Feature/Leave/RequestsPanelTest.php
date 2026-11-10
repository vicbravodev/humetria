<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Leave\Enums\RequestStatus;
use App\Modules\Leave\Http\Controllers\RequestController;
use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Leave\Support\Balances;
use App\Modules\Leave\Support\RequestPages;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;

mutates(RequestController::class, RequestPages::class);

/** The queries of the queue, whatever the number of requests, people or filters (the session and the panel frame included). */
const LEAVE_REQUESTS_QUERIES = 21;

beforeEach(function (): void {
    workIn($this->tenant = Tenant::factory()->create(['slug' => 'acme']));
    // 18:00 UTC is noon in Mexico City.
    $this->travelTo('2026-11-04 18:00:00');
    LeaveSetting::factory()->create(['tenant_id' => $this->tenant->id, 'tracking_since' => '2025-01-01']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->boss = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Beto Ruiz']);
    $this->ana = Employee::factory()->reportingTo($this->boss)->create(['name' => 'Ana Solís']);
    $this->caro = Employee::factory()->reportingTo($this->boss)->create(['name' => 'Caro Luz']);
    $this->dan = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Dan Gil']);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function leaveQueueRequest(Employee $person, string $state = 'requested', array $attributes = []): LeaveRequest
{
    $factory = LeaveRequest::factory();
    $factory = match ($state) {
        'approved' => $factory->approved(),
        'taken' => $factory->taken(),
        'rejected' => $factory->rejected(),
        'cancelled' => $factory->cancelled(),
        'escalated' => $factory->escalated(),
        default => $factory,
    };

    return $factory->create(['tenant_id' => $person->tenant_id, 'employee_id' => $person->id, 'approver_employee_id' => $person->manager_id, ...$attributes]);
}

/**
 * @param  array<string, mixed>  $query
 * @return array<string, mixed>
 */
function leaveQueueProps(array $query = []): array
{
    return test()->actingAs(test()->hr)->get(route('tenant.vacations.requests.index', [test()->tenant, ...$query]))->assertOk()->inertiaProps();
}

it('lives under the vacations of the company and is hidden from a union', function (): void {
    expect(route('tenant.vacations.requests.index', $this->tenant, absolute: false))->toBe('/admin/acme/vacations/requests');

    $union = Tenant::factory()->union()->create();

    $this->actingAs(memberOf($union, TenantRole::Owner))->get(route('tenant.vacations.requests.index', $union))->assertNotFound();
});

it('opens on what needs RH: only the requests that reached them, with why', function (): void {
    $noManager = leaveQueueRequest($this->dan, 'escalated', ['approver_employee_id' => null, 'escalation_reason' => 'no_manager']);
    $timeout = leaveQueueRequest($this->ana, 'escalated');
    // Not for RH yet, or already answered.
    leaveQueueRequest($this->caro);
    leaveQueueRequest($this->ana, 'approved', ['starts_on' => '2026-12-07', 'ends_on' => '2026-12-08']);

    $props = leaveQueueProps();

    expect($props['tab'])->toBe('attend')
        ->and($props['counts'])->toBe(['attend' => 2, 'waiting' => 1, 'approved' => 1, 'rejected' => 0, 'cancelled' => 0, 'taken' => 0])
        ->and($props['requests']['total'])->toBe(2)
        ->and(array_column($props['requests']['data'], 'id'))->toEqualCanonicalizing([$noManager->id, $timeout->id]);

    $rows = array_column($props['requests']['data'], null, 'id');

    expect($rows[$noManager->id]['reason'])->toBe('Sin jefe activo')
        ->and($rows[$noManager->id]['manager'])->toBeNull()
        ->and($rows[$timeout->id]['reason'])->toBe('Su jefe no respondió en 3 días hábiles')
        ->and($rows[$timeout->id]['manager'])->toBe('Beto Ruiz');
});

it('gives each tab its own requests', function (string $tab, string $state): void {
    $request = leaveQueueRequest($this->ana, $state);
    leaveQueueRequest($this->caro, $state === 'approved' ? 'rejected' : 'approved', ['starts_on' => '2026-12-07', 'ends_on' => '2026-12-08']);

    expect(leaveQueueProps(['tab' => $tab])['requests']['data'][0]['id'])->toBe($request->id);
})->with([
    'waiting' => ['waiting', 'requested'],
    'approved' => ['approved', 'approved'],
    'rejected' => ['rejected', 'rejected'],
    'cancelled' => ['cancelled', 'cancelled'],
    'taken' => ['taken', 'taken'],
]);

it('falls back to what needs RH for a tab it does not know', function (): void {
    expect(leaveQueueProps(['tab' => 'nope'])['tab'])->toBe('attend');
});

it('says per request who, whose it was to answer, the dates, whether they had the days and from which periods', function (): void {
    $older = LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'years_of_service' => 1, 'anniversary_on' => '2025-03-15', 'statutory_days' => 12]);
    $newer = LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'years_of_service' => 2, 'anniversary_on' => '2026-03-15', 'statutory_days' => 14]);
    $request = leaveQueueRequest($this->ana, 'escalated', ['had_balance' => true, 'allocation' => [
        ['entitlement_id' => $older->id, 'tranche' => 'statutory', 'days' => 3],
        ['entitlement_id' => $newer->id, 'tranche' => 'statutory', 'days' => 6],
    ]]);
    leaveQueueRequest($this->dan, 'escalated', ['approver_employee_id' => null, 'had_balance' => false, 'short_by' => 9, 'escalation_reason' => 'no_manager']);

    $rows = array_column(leaveQueueProps()['requests']['data'], null, 'id');

    expect($rows[$request->id])->toMatchArray([
        'name' => 'Ana Solís', 'manager' => 'Beto Ruiz', 'type' => null, 'startsOn' => '2026-11-09', 'endsOn' => '2026-11-20', 'workingDays' => 9,
        'status' => 'requested', 'statusLabel' => 'Solicitada', 'hadBalance' => true, 'periods' => [2025, 2026], 'shortBy' => 0,
        'canDecide' => true, 'canCancel' => true, 'recordedByHr' => false,
    ]);
});

it('shows RH the leave type, the note and the reason of a rejection, and a viewer none of them', function (TenantRole $role, bool $sees): void {
    $type = LeaveType::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Maternidad']);
    LeaveRequest::factory()->ofType($type)->escalated()->create(['employee_id' => $this->ana->id, 'approver_employee_id' => $this->boss->id, 'note' => 'Nota privada']);
    leaveQueueRequest($this->caro, 'rejected', ['rejection_reason' => 'Cierre de mes']);

    $this->actingAs(memberOf($this->tenant, $role));
    $response = $this->get(route('tenant.vacations.requests.index', $this->tenant))->assertOk();
    $rejected = $this->get(route('tenant.vacations.requests.index', [$this->tenant, 'tab' => 'rejected']))->assertOk();
    $sent = (string) json_encode([$response->inertiaProps(), $rejected->inertiaProps()], JSON_UNESCAPED_UNICODE);

    expect($response->inertiaProps()['requests']['data'][0]['type'])->toBe($sees ? 'Maternidad' : 'Permiso')
        ->and(str_contains($sent, 'Maternidad'))->toBe($sees)
        ->and(str_contains($sent, 'Nota privada'))->toBe($sees)
        ->and(str_contains($sent, 'Cierre de mes'))->toBe($sees)
        ->and($response->inertiaProps()['canManage'])->toBe($sees);
})->with([
    'hr' => [TenantRole::Hr, true],
    'owner' => [TenantRole::Owner, true],
    'viewer' => [TenantRole::Viewer, false],
]);

it('searches by name without caring for accents or case, and narrows by work center and area', function (): void {
    $plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    $area = Area::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Producción']);
    $joel = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Joel Álvarez', 'work_center_id' => $plant->id, 'area_id' => $area->id]);
    leaveQueueRequest($joel, 'escalated');
    leaveQueueRequest($this->ana, 'escalated');

    expect(array_column(leaveQueueProps(['q' => 'alvarez'])['requests']['data'], 'name'))->toBe(['Joel Álvarez'])
        ->and(array_column(leaveQueueProps(['center' => $plant->id])['requests']['data'], 'name'))->toBe(['Joel Álvarez'])
        ->and(array_column(leaveQueueProps(['area' => $area->id])['requests']['data'], 'name'))->toBe(['Joel Álvarez'])
        ->and(leaveQueueProps(['q' => 'solis', 'area' => $area->id])['requests']['total'])->toBe(0)
        ->and(leaveQueueProps(['center' => $plant->id])['filters']['center'])->toBe(['value' => $plant->id, 'label' => 'Planta Norte'])
        ->and(leaveQueueProps(['area' => $area->id])['filters']['area'])->toBe(['value' => $area->id, 'label' => 'Producción'])
        // The tabs follow the filters: they count what the list holds.
        ->and(leaveQueueProps(['q' => 'alvarez'])['counts']['attend'])->toBe(1);
});

it('answers a work center that is not the company\'s with a 404', function (): void {
    $foreign = WorkCenter::factory()->for(Tenant::factory()->create())->create();

    $this->actingAs($this->hr)->get(route('tenant.vacations.requests.index', [$this->tenant, 'center' => $foreign->id]))->assertNotFound();
    $this->actingAs($this->hr)->get(route('tenant.vacations.requests.index', [$this->tenant, 'center' => 'nope']))->assertNotFound();
});

it('turns the pages of the requests', function (): void {
    foreach (range(1, 12) as $day) {
        leaveQueueRequest($this->ana, 'escalated', ['starts_on' => sprintf('2027-%02d-01', $day), 'ends_on' => sprintf('2027-%02d-02', $day)]);
    }

    $props = leaveQueueProps(['page' => 2]);

    expect($props['requests']['current_page'])->toBe(2)
        ->and($props['requests']['total'])->toBe(12)
        ->and($props['requests']['data'])->toHaveCount(2);
});

it('is for whoever may view vacations and for nobody else', function (TenantRole $role, bool $allowed): void {
    $response = $this->actingAs(memberOf($this->tenant, $role))->get(route('tenant.vacations.requests.index', $this->tenant));

    $allowed ? $response->assertOk() : $response->assertForbidden();
})->with([
    'owner' => [TenantRole::Owner, true],
    'hr' => [TenantRole::Hr, true],
    'viewer' => [TenantRole::Viewer, true],
    'committee' => [TenantRole::Committee, false],
]);

it('shows nothing of another company', function (): void {
    $other = Tenant::factory()->create(['slug' => 'otra']);
    LeaveSetting::factory()->create(['tenant_id' => $other->id]);
    leaveQueueRequest(Employee::factory()->create(['tenant_id' => $other->id, 'name' => 'Extraña']), 'escalated');

    $props = leaveQueueProps();

    expect($props['requests']['data'])->toBe([])
        ->and($props['counts']['attend'])->toBe(0);

    $this->get(route('tenant.vacations.requests.index', $other))->assertNotFound();
});

it('asks the same queries whatever the number of requests and people', function (int $more): void {
    foreach (range(1, $more) as $number) {
        $person = Employee::factory()->reportingTo($this->boss)->create(['name' => "Otra {$number}"]);
        leaveQueueRequest($person, 'escalated');
        leaveQueueRequest($person, 'approved', ['starts_on' => '2027-01-11', 'ends_on' => '2027-01-12']);
    }
    $this->actingAs($this->hr);

    $this->expectsDatabaseQueryCount(LEAVE_REQUESTS_QUERIES);
    $this->get(route('tenant.vacations.requests.index', $this->tenant))->assertOk();
})->with(['a few' => [1], 'many more' => [30]]);

describe('answering and correcting', function (): void {
    beforeEach(function (): void {
        Notification::fake();
        // Ana: 3 days left of the older period, 14 of the newer one.
        $this->older = LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'years_of_service' => 1, 'anniversary_on' => '2025-03-15', 'statutory_days' => 3]);
        $this->newer = LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'years_of_service' => 2, 'anniversary_on' => '2026-03-15', 'statutory_days' => 14]);
    });

    function leaveQueueLeft(Employee $person): int
    {
        return Balances::available($person->id);
    }

    it('answers under the vacations of the company', function (): void {
        expect(route('tenant.vacations.requests.approve', ['acme', 'r1'], absolute: false))->toBe('/admin/acme/vacations/requests/r1/approve')
            ->and(route('tenant.vacations.requests.reject', ['acme', 'r1'], absolute: false))->toBe('/admin/acme/vacations/requests/r1/reject')
            ->and(route('tenant.vacations.requests.cancel', ['acme', 'r1'], absolute: false))->toBe('/admin/acme/vacations/requests/r1/cancel');
    });

    it('approves a request that reached RH and charges the ledger, audited', function (): void {
        $request = leaveQueueRequest($this->ana, 'escalated');

        $this->actingAs($this->hr)->post(route('tenant.vacations.requests.approve', [$this->tenant, $request->id]))
            ->assertRedirect()
            ->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Solicitud aprobada.']);

        expect($request->refresh()->only(['status', 'decided_by_user_id']))->toBe(['status' => RequestStatus::Approved, 'decided_by_user_id' => $this->hr->id])
            ->and(leaveQueueLeft($this->ana))->toBe(17 - 9)
            ->and(AuditEntry::query()->where('event', 'leave.request_decided_by_hr')->count())->toBe(1);
    });

    it('approves one that still waits for the manager: RH may answer at any time', function (): void {
        $request = leaveQueueRequest($this->ana);

        $this->actingAs($this->hr)->post(route('tenant.vacations.requests.approve', [$this->tenant, $request->id]))->assertRedirect();

        expect($request->refresh()->status)->toBe(RequestStatus::Approved);
    });

    it('rejects with the reason the person will read, and asks for it', function (): void {
        $request = leaveQueueRequest($this->ana, 'escalated');
        $url = route('tenant.vacations.requests.reject', [$this->tenant, $request->id]);

        $this->actingAs($this->hr)->post($url)->assertSessionHasErrors(['reason']);
        $this->post($url, ['reason' => 'corto'])->assertSessionDoesntHaveErrors();
        expect($request->refresh()->status)->toBe(RequestStatus::Rejected);

        $second = leaveQueueRequest($this->caro, 'escalated');
        $this->post(route('tenant.vacations.requests.reject', [$this->tenant, $second->id]), ['reason' => str_repeat('a', 501)])->assertSessionHasErrors(['reason']);
        expect($second->refresh()->status)->toBe(RequestStatus::Requested);
    });

    it('records the reason of a rejection and leaves the ledger alone', function (): void {
        $request = leaveQueueRequest($this->ana, 'escalated');

        $this->actingAs($this->hr)->post(route('tenant.vacations.requests.reject', [$this->tenant, $request->id]), ['reason' => 'Cierre de mes'])
            ->assertRedirect()
            ->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Solicitud rechazada.']);

        expect($request->refresh()->only(['status', 'rejection_reason']))->toBe(['status' => RequestStatus::Rejected, 'rejection_reason' => 'Cierre de mes'])
            ->and(leaveQueueLeft($this->ana))->toBe(17);
    });

    it('says why vacations cannot be approved without days, in tú, and keeps the request waiting', function (): void {
        $request = leaveQueueRequest($this->caro, 'escalated');

        $this->actingAs($this->hr)->post(route('tenant.vacations.requests.approve', [$this->tenant, $request->id]))
            ->assertRedirect()
            ->assertSessionHas('inertia.flash_data.toast', ['type' => 'error', 'message' => 'La persona no tiene días disponibles y su política no permite adelantar vacaciones.']);

        expect($request->refresh()->status)->toBe(RequestStatus::Requested);
    });

    it('tells the second answer that the request was already answered, without changing anything', function (): void {
        $request = leaveQueueRequest($this->ana, 'escalated');
        $this->actingAs($this->hr)->post(route('tenant.vacations.requests.approve', [$this->tenant, $request->id]));

        $this->post(route('tenant.vacations.requests.reject', [$this->tenant, $request->id]), ['reason' => 'Cierre de mes'])
            ->assertSessionHas('inertia.flash_data.toast', ['type' => 'error', 'message' => 'Esta solicitud ya fue respondida.']);

        expect($request->refresh()->status)->toBe(RequestStatus::Approved)
            ->and(leaveQueueLeft($this->ana))->toBe(17 - 9);
    });

    it('corrects by cancelling one already enjoyed, which gives the days back and is audited', function (): void {
        $request = leaveQueueRequest($this->ana, 'escalated');
        $this->actingAs($this->hr)->post(route('tenant.vacations.requests.approve', [$this->tenant, $request->id]));
        $request->update(['status' => RequestStatus::Taken]);

        $this->post(route('tenant.vacations.requests.cancel', [$this->tenant, $request->id]))
            ->assertRedirect()
            ->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'La solicitud quedó cancelada.']);

        expect($request->refresh()->status)->toBe(RequestStatus::Cancelled)
            ->and(leaveQueueLeft($this->ana))->toBe(17)
            ->and(AuditEntry::query()->where('event', 'leave.request_cancelled')->count())->toBe(1);
    });

    it('does not cancel what is no longer holding its dates', function (): void {
        $request = leaveQueueRequest($this->ana, 'rejected');

        $this->actingAs($this->hr)->post(route('tenant.vacations.requests.cancel', [$this->tenant, $request->id]))
            ->assertSessionHas('inertia.flash_data.toast', ['type' => 'error', 'message' => 'Esta solicitud ya no se puede cancelar.']);

        expect($request->refresh()->status)->toBe(RequestStatus::Rejected);
    });

    it('lets a viewer read the queue but not answer, and shows nobody else\'s request', function (string $action): void {
        $request = leaveQueueRequest($this->ana, 'escalated');
        $other = Tenant::factory()->create(['slug' => 'otra']);
        $stranger = leaveQueueRequest(Employee::factory()->create(['tenant_id' => $other->id]), 'escalated');
        $data = $action === 'reject' ? ['reason' => 'Cierre de mes'] : [];

        $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->post(route("tenant.vacations.requests.{$action}", [$this->tenant, $request->id]), $data)->assertForbidden();
        expect($request->refresh()->status)->toBe(RequestStatus::Requested);

        $this->actingAs($this->hr)->post(route("tenant.vacations.requests.{$action}", [$this->tenant, $stranger->id]), $data)->assertNotFound();
        expect(LeaveRequest::query()->withoutGlobalScopes()->findOrFail($stranger->id)->status)->toBe(RequestStatus::Requested);
    })->with(['approve', 'reject', 'cancel']);
});
