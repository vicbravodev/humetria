<?php

declare(strict_types=1);

use App\Modules\Leave\Actions\DecideRequest;
use App\Modules\Leave\Actions\RouteRequests;
use App\Modules\Leave\Enums\EscalationReason;
use App\Modules\Leave\Enums\RequestStatus;
use App\Modules\Leave\Jobs\RequestToDecide;
use App\Modules\Leave\Listeners\ReassignOnManagerChange;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Leave\Notifications\RequestEscalated;
use App\Modules\Leave\Notifications\RequestToDecide as ToDecideMail;
use App\Modules\Leave\Support\Decider;
use App\Modules\Organization\Actions\SetManager;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

mutates(ReassignOnManagerChange::class, RouteRequests::class);

// B12 (H14): a person's manager changes and what waited for the one they had goes to the one they have now, right then:
// not at the next 00:40 run, and not only what had not reached RH.

beforeEach(function (): void {
    workIn($this->tenant = Tenant::factory()->create(['slug' => 'autopartes']));
    $this->owner = ownerOf($this->tenant);
    LeaveSetting::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->travelTo('2026-11-19 18:00:00');
    $this->director = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Dora Díaz', 'email' => 'dora@autopartes.test']);
    $this->boss = Employee::factory()->reportingTo($this->director)->create(['name' => 'Beto Ruiz', 'email' => 'beto@autopartes.test']);
    $this->other = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Olga Pérez', 'email' => 'olga@autopartes.test']);
    $this->ana = Employee::factory()->reportingTo($this->boss)->create(['name' => 'Ana Solís']);
});

/**
 * A request of Ana waiting for her boss, in the states the factory names.
 */
function bossRequest(string ...$states): LeaveRequest
{
    $factory = LeaveRequest::factory();

    foreach ($states as $state) {
        $factory = $factory->{$state}();
    }

    return $factory->create(['tenant_id' => test()->tenant->id, 'employee_id' => test()->ana->id, 'approver_employee_id' => test()->boss->id]);
}

function moveAna(?string $toManagerId): void
{
    resolve(SetManager::class)->handle(test()->ana, $toManagerId);
}

it('gives what waited for the previous manager to the new one at once, and tells them', function (): void {
    Notification::fake();
    $request = bossRequest();

    moveAna($this->other->id);

    expect($request->refresh()->only(['status', 'approver_employee_id', 'escalated_at']))->toBe(['status' => RequestStatus::Requested, 'approver_employee_id' => $this->other->id, 'escalated_at' => null]);
    Notification::assertSentOnDemandTimes(ToDecideMail::class, 1);
    Notification::assertSentOnDemand(ToDecideMail::class, fn (ToDecideMail $mail, array $channels, Illuminate\Notifications\AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'olga@autopartes.test');
});

it('queues the email to the new manager', function (): void {
    Queue::fake();
    $request = bossRequest();

    moveAna($this->other->id);

    Queue::assertPushed(RequestToDecide::class, fn (RequestToDecide $job): bool => $job->requestId === $request->id && $job->managerId === $this->other->id);
});

it('also gives the ones that already reached RH, which stay with RH too', function (): void {
    Notification::fake();
    $request = bossRequest('escalated');

    moveAna($this->other->id);

    expect($request->refresh()->approver_employee_id)->toBe($this->other->id)
        ->and($request->escalated_at)->not->toBeNull()
        ->and($request->escalation_reason)->toBe(EscalationReason::Timeout);
    Notification::assertSentOnDemandTimes(ToDecideMail::class, 1);
    Notification::assertSentOnDemandTimes(RequestEscalated::class, 0);
});

it('takes the answer away from the previous manager and gives it to the new one', function (): void {
    Notification::fake();
    $request = bossRequest('escalated');

    moveAna($this->other->id);

    expect(fn () => resolve(DecideRequest::class)->handle($request->id, false, Decider::manager($this->boss->id), 'Ya no es mi equipo'))->toThrow(ModelNotFoundException::class)
        ->and($request->refresh()->status)->toBe(RequestStatus::Requested);

    expect(resolve(DecideRequest::class)->handle($request->id, false, Decider::manager($this->other->id), 'Cierre de mes')->status)->toBe(RequestStatus::Rejected);
});

it('goes to RH when the person is left with nobody above, as the daily routine would', function (): void {
    Notification::fake();
    $waiting = bossRequest();
    $escalated = bossRequest('escalated');

    moveAna(null);

    expect($waiting->refresh()->only(['approver_employee_id', 'escalation_reason']))->toBe(['approver_employee_id' => null, 'escalation_reason' => EscalationReason::NoManager])
        ->and($waiting->escalated_at)->not->toBeNull()
        ->and($escalated->refresh()->only(['approver_employee_id', 'escalation_reason']))->toBe(['approver_employee_id' => null, 'escalation_reason' => EscalationReason::Timeout]);
    // The owner is told of the one that just reached RH, not again of the one that already had.
    Notification::assertSentOnDemandTimes(RequestEscalated::class, 1);
    Notification::assertSentOnDemandTimes(ToDecideMail::class, 0);
});

it('leaves alone what is not waiting for the previous manager of that person', function (): void {
    Notification::fake();
    $decided = bossRequest('approved');
    $cancelled = bossRequest('cancelled');
    $withRh = bossRequest('escalated');
    $withRh->update(['approver_employee_id' => null]);
    $ofAnother = LeaveRequest::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => Employee::factory()->reportingTo($this->boss)->create()->id, 'approver_employee_id' => $this->boss->id]);
    $withOther = bossRequest();
    $withOther->update(['approver_employee_id' => $this->director->id]);

    moveAna($this->other->id);

    expect($decided->refresh()->approver_employee_id)->toBe($this->boss->id)
        ->and($cancelled->refresh()->approver_employee_id)->toBe($this->boss->id)
        ->and($withRh->refresh()->approver_employee_id)->toBeNull()
        ->and($ofAnother->refresh()->approver_employee_id)->toBe($this->boss->id)
        ->and($withOther->refresh()->approver_employee_id)->toBe($this->director->id);
    Notification::assertNothingSent();
});

it('changes nothing when the person had no manager before', function (): void {
    Notification::fake();
    $this->ana->update(['manager_id' => null]);
    $withRh = bossRequest('escalated');
    $withRh->update(['approver_employee_id' => null]);

    moveAna($this->other->id);

    expect($withRh->refresh()->approver_employee_id)->toBeNull();
    Notification::assertNothingSent();
});

it('changes nothing when the new manager is who answers anyway, because the previous one left', function (): void {
    Notification::fake();
    $request = bossRequest();
    $this->boss->update(['deactivated_at' => now()]);
    $request->update(['approver_employee_id' => $this->director->id]);

    moveAna($this->director->id);

    expect($request->refresh()->approver_employee_id)->toBe($this->director->id);
    Notification::assertNothingSent();
});

it('leaves to the departure of a manager what that does, which keeps with RH what already reached it', function (): void {
    Notification::fake();
    $escalated = bossRequest('escalated');
    $this->boss->update(['deactivated_at' => now()]);

    moveAna($this->other->id);

    expect($escalated->refresh()->approver_employee_id)->toBe($this->boss->id);
    Notification::assertNothingSent();
});

it('leaves everything alone in a company that does not use vacations', function (): void {
    Notification::fake();
    $blocked = Tenant::factory()->suspended()->create();
    workIn($blocked);
    LeaveSetting::factory()->create(['tenant_id' => $blocked->id]);
    $boss = Employee::factory()->create(['tenant_id' => $blocked->id, 'email' => 'jefe@bloqueada.test']);
    $new = Employee::factory()->create(['tenant_id' => $blocked->id, 'email' => 'nuevo@bloqueada.test']);
    $person = Employee::factory()->reportingTo($boss)->create();
    $request = LeaveRequest::factory()->create(['tenant_id' => $blocked->id, 'employee_id' => $person->id, 'approver_employee_id' => $boss->id]);

    resolve(SetManager::class)->handle($person, $new->id);

    expect($request->refresh()->approver_employee_id)->toBe($boss->id);
    Notification::assertNothingSent();
});

it('logs each reassignment with ids only', function (): void {
    Notification::fake();
    $request = bossRequest();
    $lines = [];
    Log::listen(function (MessageLogged $logged) use (&$lines): void {
        $lines[] = $logged->message.' '.json_encode($logged->context, JSON_THROW_ON_ERROR);
    });

    moveAna($this->other->id);

    expect(array_values(array_filter($lines, fn (string $line): bool => str_starts_with($line, 'leave.'))))->toBe([
        'leave.request_reassigned '.json_encode(['request_id' => $request->id, 'approver_employee_id' => $this->other->id], JSON_THROW_ON_ERROR),
    ]);
});
