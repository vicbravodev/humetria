<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Leave\Actions\TrimRequestsAfter;
use App\Modules\Leave\Enums\EntryKind;
use App\Modules\Leave\Enums\EscalationReason;
use App\Modules\Leave\Enums\RequestStatus;
use App\Modules\Leave\Enums\Tranche;
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
use Carbon\CarbonImmutable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

mutates(CloseOnDeactivation::class, TrimRequestsAfter::class);

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

/**
 * A vacation approved or enjoyed: it charged its working days to the period, as the ledger would have it.
 *
 * @param  array<string, mixed>  $attributes
 */
function chargedRequest(Employee $person, LeaveEntitlement $period, string $state, array $attributes, int $charged): LeaveRequest
{
    $request = requestOf($person, $state, $attributes);
    LeaveEntry::factory()->of($period)->taken($charged)->create(['leave_request_id' => $request->id]);

    return $request;
}

it('cancels a vacation already enjoyed that started after the day the person left, and gives its days back', function (): void {
    $enjoyed = chargedRequest($this->ana, $this->period, 'taken', ['starts_on' => '2026-10-19', 'ends_on' => '2026-10-23', 'working_days' => 5], 5);

    resolve(DeactivateEmployee::class)->handle($this->ana, effectiveOn: CarbonImmutable::parse('2026-10-15'));

    expect($enjoyed->refresh()->status)->toBe(RequestStatus::Cancelled)
        ->and(Balances::available($this->ana->id))->toBe(14);
});

it('gives back the working days after the departure of a vacation that began before it, and shortens it', function (string $state, string $startsOn, string $endsOn, int $workingDays, string $effectiveOn, int $returned): void {
    $request = chargedRequest($this->ana, $this->period, $state, ['starts_on' => $startsOn, 'ends_on' => $endsOn, 'working_days' => $workingDays], $workingDays);

    resolve(DeactivateEmployee::class)->handle($this->ana, effectiveOn: CarbonImmutable::parse($effectiveOn));

    expect($request->refresh()->only(['status', 'working_days']))->toBe(['status' => RequestStatus::from($state), 'working_days' => $workingDays - $returned])
        ->and($request->ends_on->toDateString())->toBe($effectiveOn)
        ->and(LeaveEntry::query()->where('kind', EntryKind::Adjustment)->get()->map(fn (LeaveEntry $entry): array => [$entry->leave_request_id, $entry->days, $entry->recorded_by])->all())->toBe([[$request->id, $returned, null]])
        ->and(Balances::available($this->ana->id))->toBe(14 - ($workingDays - $returned))
        ->and(AuditEntry::query()->where('event', 'leave.request_trimmed')->sole()->metadata)->toEqual(['employee_id' => $this->ana->id, 'was' => $state, 'days_returned' => $returned]);
})->with([
    // Monday 26 October to Friday 6 November, gone on Wednesday the 28th: three worked, the other seven go back.
    'approved, going on' => ['approved', '2026-10-26', '2026-11-06', 10, '2026-10-28', 7],
    'enjoyed, already over' => ['taken', '2026-10-19', '2026-10-23', 5, '2026-10-21', 2],
    // The day they left is theirs; the weekend after it is not a working day.
    'the last day it began' => ['approved', '2026-10-26', '2026-11-06', 10, '2026-10-26', 9],
]);

it('cancels a vacation whose only days after the start are not working days of the person', function (): void {
    // Saturday 24 to Friday 30 October, gone on the Saturday: nothing of it was worked.
    $request = chargedRequest($this->ana, $this->period, 'approved', ['starts_on' => '2026-10-24', 'ends_on' => '2026-10-30', 'working_days' => 5], 5);

    resolve(DeactivateEmployee::class)->handle($this->ana, effectiveOn: CarbonImmutable::parse('2026-10-24'));

    expect($request->refresh()->status)->toBe(RequestStatus::Cancelled)
        ->and(Balances::available($this->ana->id))->toBe(14);
});

it('gives the days of a trimmed vacation back to the newest period first', function (): void {
    $old = LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->boss->id, 'years_of_service' => 1, 'anniversary_on' => '2025-01-01', 'statutory_days' => 12]);
    $new = LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->boss->id, 'years_of_service' => 2, 'anniversary_on' => '2026-01-01', 'statutory_days' => 14, 'extra_days' => 2]);
    $request = requestOf($this->boss, 'approved', ['starts_on' => '2026-10-26', 'ends_on' => '2026-11-06', 'working_days' => 10, 'approver_employee_id' => null]);
    LeaveEntry::factory()->of($old)->taken(6)->create(['leave_request_id' => $request->id]);
    LeaveEntry::factory()->of($new)->taken(3)->create(['leave_request_id' => $request->id]);
    LeaveEntry::factory()->of($new)->taken(1, Tranche::Extra)->create(['leave_request_id' => $request->id]);

    resolve(DeactivateEmployee::class)->handle($this->boss, $this->director->id, CarbonImmutable::parse('2026-10-28'));

    // Seven to give back: the extra day and the three statutory ones of the newest, then the three of the oldest.
    expect(LeaveEntry::query()->where('kind', EntryKind::Adjustment)->get()->map(fn (LeaveEntry $entry): array => [$entry->leave_entitlement_id, $entry->tranche, $entry->days])->all())->toEqualCanonicalizing([
        [$new->id, Tranche::Extra, 1], [$old->id, Tranche::Statutory, 3], [$new->id, Tranche::Statutory, 3],
    ]);
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
