<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Leave\Actions\DecideRequest;
use App\Modules\Leave\Enums\EscalationReason;
use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveEntry;
use App\Modules\Leave\Models\LeavePolicy;
use App\Modules\Leave\Models\LeavePolicyAssignment;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Leave\Support\Balances;
use App\Modules\Leave\Support\Decider;
use App\Modules\Leave\Support\PortalTeamPages;
use App\Modules\Organization\Models\Employee;
use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

mutates(PortalTeamPages::class);

// Opening the link and checking the company uses Vacaciones, then the count and the page of the requests and, once for
// the whole page, their leave types, their people, their balances and their policies.
const PORTAL_TEAM_REQUESTS_QUERIES = 16;

beforeEach(function (): void {
    workIn($this->tenant = Tenant::factory()->create(['trade_name' => 'Acme']));
    // 18:00 UTC is noon in Mexico City.
    $this->travelTo('2026-11-02 18:00:00');
    Notification::fake();
    LeaveSetting::factory()->create(['tenant_id' => $this->tenant->id, 'tracking_since' => '2025-01-01']);
    // Beto leads Ana and Caro; Dora, beside him, leads Dan.
    $this->boss = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Beto Ruiz']);
    $this->ana = Employee::factory()->reportingTo($this->boss)->create(['name' => 'Ana Solís']);
    $this->caro = Employee::factory()->reportingTo($this->boss)->create(['name' => 'Caro Luz']);
    $this->dora = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Dora Paz']);
    $this->dan = Employee::factory()->reportingTo($this->dora)->create(['name' => 'Dan Gil']);
    // Ana: 3 days left of the older period and 14 of the newer one. Caro and Dan have none.
    $older = LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'years_of_service' => 1, 'anniversary_on' => '2025-03-15', 'statutory_days' => 12]);
    LeaveEntry::factory()->of($older)->taken(9)->create();
    LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'years_of_service' => 2, 'anniversary_on' => '2026-03-15', 'statutory_days' => 14]);
    $this->token = resolve(IssuePortalLink::class)->handle($this->boss->id);
});

/**
 * A request waiting for whoever leads the person: 9 working days of vacations unless told otherwise.
 *
 * @param  array<string, mixed>  $attributes
 */
function leaveTeamRequest(Employee $person, array $attributes = []): LeaveRequest
{
    return LeaveRequest::factory()->create(['tenant_id' => $person->tenant_id, 'employee_id' => $person->id, 'approver_employee_id' => $person->manager_id, ...$attributes]);
}

function leaveTeamAllowsAdvances(): void
{
    $policy = LeavePolicy::factory()->create(['tenant_id' => test()->tenant->id, 'allows_advance' => true]);
    LeavePolicyAssignment::factory()->create(['tenant_id' => test()->tenant->id, 'leave_policy_id' => $policy->id]);
}

function leaveTeamStatus(LeaveRequest $request): string
{
    return LeaveRequest::query()->withoutGlobalScopes()->findOrFail($request->id)->status->value;
}

it('lives under the personal link of the manager, throttled as the rest of the portal', function (): void {
    expect(route('portal.vacations.team.show', 'abc', absolute: false))->toBe('/portal/abc/vacations/team')
        ->and(route('portal.vacations.team.approve', ['abc', 'r1'], absolute: false))->toBe('/portal/abc/vacations/team/r1/approve')
        ->and(route('portal.vacations.team.reject', ['abc', 'r1'], absolute: false))->toBe('/portal/abc/vacations/team/r1/reject')
        ->and(Route::getRoutes()->getByName('portal.vacations.team.show')?->gatherMiddleware())->toContain('throttle:portal-views')
        ->and(Route::getRoutes()->getByName('portal.vacations.team.approve')?->gatherMiddleware())->toContain('throttle:portal-actions')
        ->and(Route::getRoutes()->getByName('portal.vacations.team.reject')?->gatherMiddleware())->toContain('throttle:portal-actions');
});

it('shows a manager only the requests that wait for them, the oldest first, with where the days would come from', function (): void {
    $type = LeaveType::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Paternidad']);
    $leave = leaveTeamRequest($this->caro, ['requested_on' => '2026-11-01', 'starts_on' => '2026-12-01', 'ends_on' => '2026-12-02', 'working_days' => 2, 'leave_type_id' => $type->id, 'had_balance' => null]);
    $vacations = leaveTeamRequest($this->ana, ['requested_on' => '2026-10-30', 'note' => 'Viaje familiar']);
    // Not theirs to answer: a peer's, one already answered, one that went to RH with nobody above.
    leaveTeamRequest($this->dan);
    LeaveRequest::factory()->rejected()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'approver_employee_id' => $this->boss->id, 'starts_on' => '2026-06-01', 'ends_on' => '2026-06-02']);
    leaveTeamRequest($this->caro, ['approver_employee_id' => null, 'starts_on' => '2027-01-11', 'ends_on' => '2027-01-12']);

    $this->get(route('portal.vacations.team.show', $this->token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('leave/portal-team')
            ->where('token', $this->token)
            ->where('company', 'Acme')
            ->where('name', 'Beto Ruiz')
            ->where('requests.total', 2)
            ->where('requests.per_page', 10)
            ->where('requests.data', [
                ['id' => $vacations->id, 'name' => 'Ana Solís', 'type' => null, 'startsOn' => '2026-11-09', 'endsOn' => '2026-11-20', 'workingDays' => 9, 'note' => 'Viaje familiar', 'periods' => [['year' => 2025, 'days' => 3], ['year' => 2026, 'days' => 6]], 'shortBy' => 0, 'allowsAdvance' => false, 'canApprove' => true],
                ['id' => $leave->id, 'name' => 'Caro Luz', 'type' => 'Paternidad', 'startsOn' => '2026-12-01', 'endsOn' => '2026-12-02', 'workingDays' => 2, 'note' => null, 'periods' => [], 'shortBy' => 0, 'allowsAdvance' => false, 'canApprove' => true],
            ]));
});

it('adds up the tranches of a period and says how many days are missing', function (): void {
    LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->caro->id, 'years_of_service' => 1, 'anniversary_on' => '2026-02-01', 'statutory_days' => 2, 'extra_days' => 3]);
    leaveTeamRequest($this->caro);

    $this->get(route('portal.vacations.team.show', $this->token))
        ->assertInertia(fn (Assert $page) => $page
            ->where('requests.data.0.periods', [['year' => 2026, 'days' => 5]])
            ->where('requests.data.0.shortBy', 4)
            ->where('requests.data.0.canApprove', false));
});

it('offers to approve vacations without the days only when the policy of the person gives days in advance', function (bool $advances): void {
    if ($advances) {
        leaveTeamAllowsAdvances();
    }

    leaveTeamRequest($this->caro, ['starts_on' => '2026-11-09', 'ends_on' => '2026-11-12', 'working_days' => 4, 'had_balance' => false]);

    $this->get(route('portal.vacations.team.show', $this->token))
        ->assertInertia(fn (Assert $page) => $page
            ->where('requests.data.0.periods', [])
            ->where('requests.data.0.shortBy', 4)
            ->where('requests.data.0.allowsAdvance', $advances)
            ->where('requests.data.0.canApprove', $advances));
})->with([false, true]);

it('shows whoever has nothing to answer an empty list', function (): void {
    leaveTeamRequest($this->ana);

    $this->get(route('portal.vacations.team.show', resolve(IssuePortalLink::class)->handle($this->ana->id)))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('name', 'Ana Solís')->where('requests.total', 0)->where('requests.data', []));
});

it('turns the pages of the requests', function (): void {
    foreach (range(1, 12) as $day) {
        leaveTeamRequest($this->ana, ['requested_on' => sprintf('2026-10-%02d', $day), 'starts_on' => sprintf('2027-%02d-01', $day), 'ends_on' => sprintf('2027-%02d-02', $day)]);
    }

    $this->get(route('portal.vacations.team.show', [$this->token, 'page' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('requests.total', 12)
            ->where('requests.current_page', 2)
            ->has('requests.data', 2)
            ->where('requests.data.1.startsOn', '2027-12-01'));
});

it('reads the page with the same queries whatever the number of requests', function (int $requests): void {
    $type = LeaveType::factory()->create(['tenant_id' => $this->tenant->id]);

    foreach (range(1, $requests) as $month) {
        leaveTeamRequest($month % 2 === 0 ? $this->ana : $this->caro, ['starts_on' => sprintf('2027-%02d-01', $month), 'ends_on' => sprintf('2027-%02d-02', $month), ...($month % 3 === 0 ? ['leave_type_id' => $type->id, 'had_balance' => null] : [])]);
    }

    $this->expectsDatabaseQueryCount(PORTAL_TEAM_REQUESTS_QUERIES);

    $this->get(route('portal.vacations.team.show', $this->token))->assertOk();
})->with([3, 9]);

it('sends nothing of who did not ask, and nobody\'s id', function (): void {
    LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->caro->id, 'anniversary_on' => '2026-02-01', 'statutory_days' => 12]);
    leaveTeamRequest($this->ana);
    leaveTeamRequest($this->dan, ['note' => 'Nota de Dan']);

    $sent = (string) json_encode($this->get(route('portal.vacations.team.show', $this->token))->assertOk()->inertiaProps(), JSON_UNESCAPED_UNICODE);

    expect($sent)->toContain('Ana Solís')
        ->not->toContain('Caro Luz', 'Dan Gil', 'Nota de Dan', $this->ana->id, $this->caro->id, $this->boss->id, 'premium', 'amount', 'salary');
});

it('approves: the request changes and the days are charged to the ledger, the oldest period first', function (): void {
    $request = leaveTeamRequest($this->ana);

    $this->post(route('portal.vacations.team.approve', [$this->token, $request->id]))
        ->assertRedirect(route('portal.vacations.team.show', $this->token))
        ->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Solicitud aprobada.']);

    $request->refresh();
    expect($request->status->value)->toBe('approved')
        ->and($request->decided_by_employee_id)->toBe($this->boss->id)
        ->and($request->decided_by_user_id)->toBeNull()
        ->and(array_map(fn ($period): int => $period->left(), Balances::of([$this->ana->id])[$this->ana->id]))->toBe([0, 8]);
});

it('rejects with the reason, which the person will read', function (): void {
    $request = leaveTeamRequest($this->ana);

    $this->post(route('portal.vacations.team.reject', [$this->token, $request->id]), ['reason' => 'Cierre de mes: tome otra semana.'])
        ->assertRedirect(route('portal.vacations.team.show', $this->token))
        ->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Solicitud rechazada.']);

    $request->refresh();
    expect($request->status->value)->toBe('rejected')
        ->and($request->rejection_reason)->toBe('Cierre de mes: tome otra semana.')
        ->and($request->decided_by_employee_id)->toBe($this->boss->id)
        ->and(LeaveEntry::query()->where('leave_request_id', $request->id)->count())->toBe(0);
});

it('asks in Spanish for the reason of a rejection', function (array $data, string $message): void {
    $request = leaveTeamRequest($this->ana);

    $this->from(route('portal.vacations.team.show', $this->token))
        ->post(route('portal.vacations.team.reject', [$this->token, $request->id]), $data)
        ->assertRedirect(route('portal.vacations.team.show', $this->token))
        ->assertSessionHasErrors(['reason' => $message]);

    expect(leaveTeamStatus($request))->toBe('requested');
})->with([
    'none' => [[], 'El campo motivo es obligatorio.'],
    'too short' => [['reason' => 'No.'], 'El campo motivo debe tener al menos 5 caracteres.'],
    'too long' => [['reason' => str_repeat('a', 501)], 'El campo motivo no debe tener más de 500 caracteres.'],
]);

it("answers 'not found' to a request that is not theirs to answer, and changes nothing", function (string $whose): void {
    $other = Tenant::factory()->create();
    $request = match ($whose) {
        // Dan reports to Dora, beside Beto.
        'of a peer' => leaveTeamRequest($this->dan),
        'given to another manager' => leaveTeamRequest($this->ana, ['approver_employee_id' => $this->dora->id]),
        'with RH, nobody above' => leaveTeamRequest($this->ana, ['approver_employee_id' => null, 'escalated_at' => now(), 'escalation_reason' => EscalationReason::NoManager]),
        'of another company' => LeaveRequest::factory()->create(['tenant_id' => $other->id]),
    };

    $this->post(route('portal.vacations.team.approve', [$this->token, $request->id]))->assertNotFound();
    $this->post(route('portal.vacations.team.reject', [$this->token, $request->id]), ['reason' => 'Cierre de mes'])->assertNotFound();

    expect(leaveTeamStatus($request))->toBe('requested')
        ->and(LeaveEntry::query()->withoutGlobalScopes()->where('leave_request_id', $request->id)->count())->toBe(0);
})->with(['of a peer', 'given to another manager', 'with RH, nobody above', 'of another company']);

it('lets nobody answer their own request, nor one named by something that is not an id', function (): void {
    $request = leaveTeamRequest($this->ana);
    $anas = resolve(IssuePortalLink::class)->handle($this->ana->id);

    $this->post(route('portal.vacations.team.approve', [$anas, $request->id]))->assertNotFound();
    $this->post(route('portal.vacations.team.approve', [$this->token, 'not-an-id']))->assertNotFound();
    $this->post(route('portal.vacations.team.reject', [$this->token, 'not-an-id']), ['reason' => 'Cierre de mes'])->assertNotFound();

    expect(leaveTeamStatus($request))->toBe('requested');
});

it('gives a request to its new manager: the one before can no longer answer it', function (): void {
    $request = leaveTeamRequest($this->ana);
    $request->update(['approver_employee_id' => $this->dora->id]);

    $this->post(route('portal.vacations.team.approve', [$this->token, $request->id]))->assertNotFound();
    expect(leaveTeamStatus($request))->toBe('requested');

    $this->post(route('portal.vacations.team.approve', [resolve(IssuePortalLink::class)->handle($this->dora->id), $request->id]))->assertRedirect();
    expect(leaveTeamStatus($request))->toBe('approved');
});

it('still lets the manager answer what reached RH because they took too long, once', function (): void {
    $request = LeaveRequest::factory()->escalated()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'approver_employee_id' => $this->boss->id]);

    $this->post(route('portal.vacations.team.approve', [$this->token, $request->id]))
        ->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Solicitud aprobada.']);

    // A second answer, of any kind, changes nothing and charges nothing.
    $this->post(route('portal.vacations.team.approve', [$this->token, $request->id]))
        ->assertRedirect(route('portal.vacations.team.show', $this->token))
        ->assertSessionHas('inertia.flash_data.toast', ['type' => 'error', 'message' => 'Esta solicitud ya fue respondida.']);
    $this->post(route('portal.vacations.team.reject', [$this->token, $request->id]), ['reason' => 'Cierre de mes'])
        ->assertRedirect(route('portal.vacations.team.show', $this->token))
        ->assertSessionHas('inertia.flash_data.toast', ['type' => 'error', 'message' => 'Esta solicitud ya fue respondida.']);

    expect(leaveTeamStatus($request))->toBe('approved')
        ->and((int) LeaveEntry::query()->where('leave_request_id', $request->id)->sum('days'))->toBe(-9);
});

it('says a request RH answered first was already answered', function (): void {
    $request = LeaveRequest::factory()->escalated()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'approver_employee_id' => $this->boss->id]);
    resolve(DecideRequest::class)->handle($request->id, false, Decider::hr(User::factory()->create()->id), 'Cierre de mes');

    $this->post(route('portal.vacations.team.approve', [$this->token, $request->id]))
        ->assertRedirect(route('portal.vacations.team.show', $this->token))
        ->assertSessionHas('inertia.flash_data.toast', ['type' => 'error', 'message' => 'Esta solicitud ya fue respondida.']);

    expect(leaveTeamStatus($request))->toBe('rejected')
        ->and(LeaveEntry::query()->where('leave_request_id', $request->id)->count())->toBe(0);
});

it('refuses, in words, to approve vacations without the days when the policy gives none in advance', function (): void {
    $request = leaveTeamRequest($this->caro, ['starts_on' => '2026-11-09', 'ends_on' => '2026-11-12', 'working_days' => 4, 'had_balance' => false]);

    $this->post(route('portal.vacations.team.approve', [$this->token, $request->id]))
        ->assertRedirect(route('portal.vacations.team.show', $this->token))
        ->assertSessionHas('inertia.flash_data.toast', ['type' => 'error', 'message' => 'No tiene días disponibles y su política no permite adelantar vacaciones.']);

    expect(leaveTeamStatus($request))->toBe('requested')
        ->and(LeaveEntry::query()->where('leave_request_id', $request->id)->count())->toBe(0);
});

it("answers 'not found' to a link that is not valid, and in a company that does not use Vacaciones", function (): void {
    $request = leaveTeamRequest($this->ana);

    $this->get(route('portal.vacations.team.show', 'made-up'))->assertNotFound();
    $this->post(route('portal.vacations.team.approve', ['made-up', $request->id]))->assertNotFound();
    $this->post(route('portal.vacations.team.reject', ['made-up', $request->id]), ['reason' => 'Cierre de mes'])->assertNotFound();

    LeaveSetting::query()->delete();

    $this->get(route('portal.vacations.team.show', $this->token))->assertNotFound();
    $this->post(route('portal.vacations.team.approve', [$this->token, $request->id]))->assertNotFound();

    expect(leaveTeamStatus($request))->toBe('requested');
});

it('tells the person, in «Mis vacaciones», how many requests wait for their answer', function (): void {
    leaveTeamRequest($this->ana);
    leaveTeamRequest($this->caro);
    leaveTeamRequest($this->dan);

    $this->get(route('portal.vacations.show', $this->token))->assertInertia(fn (Assert $page) => $page->where('teamPending', 2));
    $this->get(route('portal.vacations.show', resolve(IssuePortalLink::class)->handle($this->ana->id)))->assertInertia(fn (Assert $page) => $page->where('teamPending', 0));
});
