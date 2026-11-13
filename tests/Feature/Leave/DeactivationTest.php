<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Leave\Enums\EntryKind;
use App\Modules\Leave\Enums\EscalationReason;
use App\Modules\Leave\Enums\RequestStatus;
use App\Modules\Leave\Listeners\CloseOnDeactivation;
use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveEntry;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Notifications\RequestEscalated;
use App\Modules\Leave\Notifications\RequestToDecide;
use App\Modules\Leave\Support\Balances;
use App\Modules\Leave\Support\Decider;
use App\Modules\Organization\Actions\DeactivateEmployee;
use App\Modules\Organization\Events\EmployeeDeactivated;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

mutates(CloseOnDeactivation::class);

beforeEach(function (): void {
    workIn($this->tenant = Tenant::factory()->create());
    $this->owner = ownerOf($this->tenant);
    // Monday 2 November 2026, noon in Mexico City.
    $this->travelTo('2026-11-02 18:00:00');
    Notification::fake();
    $this->director = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'email' => 'dora@autopartes.test']);
    $this->boss = Employee::factory()->reportingTo($this->director)->create();
    $this->ana = Employee::factory()->reportingTo($this->boss)->create();
    $this->period = LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'statutory_days' => 14]);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function requestOf(Employee $person, string $state = 'requested', array $attributes = []): LeaveRequest
{
    $factory = LeaveRequest::factory();

    return ($state === 'requested' ? $factory : $factory->{$state}())->create(['tenant_id' => $person->tenant_id, 'employee_id' => $person->id, 'approver_employee_id' => $person->manager_id, ...$attributes]);
}

function letGo(Employee $person, ?string $reassignTo = null): void
{
    resolve(DeactivateEmployee::class)->handle($person, $reassignTo);
}

it('says the system decides as RH, with no user', function (): void {
    expect(get_object_vars(Decider::system()))->toBe(['employeeId' => null, 'userId' => null, 'isHr' => true]);
});

it('cancels what the person who left was waiting for and gives back the days of what had not started', function (): void {
    $waiting = requestOf($this->ana, attributes: ['starts_on' => '2026-12-01', 'ends_on' => '2026-12-02', 'working_days' => 2]);
    $future = requestOf($this->ana, 'approved', ['starts_on' => '2026-11-03', 'ends_on' => '2026-11-06', 'working_days' => 4]);
    LeaveEntry::factory()->of($this->period)->taken(4)->create(['leave_request_id' => $future->id]);
    $enjoyed = requestOf($this->ana, 'taken', ['starts_on' => '2026-10-19', 'ends_on' => '2026-10-23', 'working_days' => 5]);
    LeaveEntry::factory()->of($this->period)->taken(5)->create(['leave_request_id' => $enjoyed->id]);
    $started = requestOf($this->ana, 'approved', ['starts_on' => '2026-11-02', 'ends_on' => '2026-11-02', 'working_days' => 1]);
    LeaveEntry::factory()->of($this->period)->taken(1)->create(['leave_request_id' => $started->id]);
    $rejected = requestOf($this->ana, 'rejected', ['starts_on' => '2026-12-07', 'ends_on' => '2026-12-08', 'working_days' => 2]);

    letGo($this->ana);

    expect(array_map(fn (LeaveRequest $request): RequestStatus => $request->refresh()->status, [$waiting, $future, $enjoyed, $started, $rejected]))
        ->toBe([RequestStatus::Cancelled, RequestStatus::Cancelled, RequestStatus::Taken, RequestStatus::Approved, RequestStatus::Rejected])
        ->and(LeaveEntry::query()->where('kind', EntryKind::Adjustment)->get()->map(fn (LeaveEntry $entry): array => [$entry->leave_request_id, $entry->days, $entry->recorded_by])->all())->toBe([[$future->id, 4, null]])
        // 14 less the 5 enjoyed and the day that already started.
        ->and(Balances::available($this->ana->id))->toBe(8)
        ->and(AuditEntry::query()->where('event', 'leave.request_cancelled')->count())->toBe(2);
});

it('leaves the requests of everybody else as they are', function (): void {
    $colleague = Employee::factory()->reportingTo($this->boss)->create();
    $theirs = requestOf($colleague);
    $elsewhere = LeaveRequest::factory()->create();

    letGo($this->ana);

    expect($theirs->refresh()->only(['status', 'approver_employee_id']))->toBe(['status' => RequestStatus::Requested, 'approver_employee_id' => $this->boss->id])
        ->and(LeaveRequest::query()->withoutGlobalScopes()->whereKey($elsewhere->id)->sole()->status)->toBe(RequestStatus::Requested);
    Notification::assertNothingSent();
});

it('passes what waited for a manager who left to whoever their team reports to now, right away, and emails them', function (string $to): void {
    $peer = Employee::factory()->reportingTo($this->director)->create(['email' => 'paco@autopartes.test']);
    $heir = $to === 'the director' ? $this->director : $peer;
    $request = requestOf($this->ana);
    $answered = requestOf($this->ana, 'approved', ['starts_on' => '2026-12-01', 'ends_on' => '2026-12-02', 'working_days' => 2]);
    $escalated = requestOf($this->ana, 'escalated', ['starts_on' => '2026-12-07', 'ends_on' => '2026-12-08', 'working_days' => 2]);

    letGo($this->boss, $heir->id);

    expect($request->refresh()->only(['status', 'approver_employee_id', 'escalated_at']))->toBe(['status' => RequestStatus::Requested, 'approver_employee_id' => $heir->id, 'escalated_at' => null])
        // What was answered or already with RH keeps who it was with.
        ->and($answered->refresh()->approver_employee_id)->toBe($this->boss->id)
        ->and($escalated->refresh()->approver_employee_id)->toBe($this->boss->id);
    Notification::assertSentOnDemandTimes(RequestToDecide::class, 1);
    Notification::assertSentOnDemand(RequestToDecide::class, fn (RequestToDecide $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === $heir->email);
})->with(['the director', 'a peer']);

it('goes up past the manager who left when their team still hangs from them, and to RH when nobody is above', function (): void {
    $ofAna = requestOf($this->ana);
    $ofBoss = requestOf($this->boss);

    // Deactivated without moving their teams: the line still names them.
    $this->boss->update(['deactivated_at' => now()]);
    event(new EmployeeDeactivated($this->tenant->id, $this->boss->id, now()->toImmutable()));
    $this->director->update(['deactivated_at' => now()]);
    event(new EmployeeDeactivated($this->tenant->id, $this->director->id, now()->toImmutable()));

    expect($ofBoss->refresh()->status)->toBe(RequestStatus::Cancelled)
        ->and($ofAna->refresh()->only(['status', 'approver_employee_id', 'escalation_reason']))->toBe(['status' => RequestStatus::Requested, 'approver_employee_id' => null, 'escalation_reason' => EscalationReason::NoManager])
        ->and($ofAna->escalated_at?->toDateTimeString())->toBe('2026-11-02 18:00:00');
    // First to the director, then, when she left too, to RH.
    Notification::assertSentOnDemandTimes(RequestToDecide::class, 1);
    Notification::assertSentOnDemandTimes(RequestEscalated::class, 1);
    Notification::assertSentOnDemand(RequestEscalated::class, fn (RequestEscalated $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === $this->owner->email);
});

it('does both for a manager who also had requests of their own', function (): void {
    $own = requestOf($this->boss);
    $ofTeam = requestOf($this->ana);

    letGo($this->boss, $this->director->id);

    expect($own->refresh()->status)->toBe(RequestStatus::Cancelled)
        ->and($ofTeam->refresh()->approver_employee_id)->toBe($this->director->id);
});

it('works in the company of the event, whatever company was current', function (): void {
    $request = requestOf($this->ana);
    $this->ana->update(['deactivated_at' => now()]);
    workIn(Tenant::factory()->create());

    event(new EmployeeDeactivated($this->tenant->id, $this->ana->id, now()->toImmutable()));

    expect(LeaveRequest::query()->withoutGlobalScopes()->whereKey($request->id)->sole()->status)->toBe(RequestStatus::Cancelled);
});

it('undoes everything, emails included, when the deactivation does not go through', function (): void {
    $own = requestOf($this->boss);
    $ofTeam = requestOf($this->ana);

    try {
        DB::transaction(function (): void {
            letGo($this->boss, $this->director->id);

            throw new RuntimeException('The deactivation failed.');
        });
    } catch (RuntimeException) {
        // The transaction that deactivated them rolled back.
    }

    expect($own->refresh()->status)->toBe(RequestStatus::Requested)
        ->and($ofTeam->refresh()->approver_employee_id)->toBe($this->boss->id);
    Notification::assertNothingSent();
});
