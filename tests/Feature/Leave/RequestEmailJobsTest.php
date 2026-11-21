<?php

declare(strict_types=1);

use App\Modules\Leave\Actions\RequestLeave;
use App\Modules\Leave\Jobs\RequestDecided;
use App\Modules\Leave\Jobs\RequestEscalated;
use App\Modules\Leave\Jobs\RequestToDecide;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Notifications\RequestDecided as DecidedMail;
use App\Modules\Leave\Notifications\RequestEscalated as EscalatedMail;
use App\Modules\Leave\Notifications\RequestToDecide as ToDecideMail;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\WorkerAccess\Contracts\WorkerLinks;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

mutates(RequestToDecide::class, RequestEscalated::class, RequestDecided::class);

// The three emails of a request (H05, B4): each one is a job, queued once the request is saved, that reads the request again
// when it runs, so one that changed in between (answered, cancelled, given to another manager) tells nobody what is no
// longer true. A mail server that fails is retried by the queue, not swallowed.

beforeEach(function (): void {
    workIn($this->tenant = Tenant::factory()->create(['trade_name' => 'Autopartes Demo', 'slug' => 'autopartes']));
    $this->owner = ownerOf($this->tenant);
    $this->travelTo('2026-11-02 18:00:00');
    $this->director = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Dora Díaz', 'email' => 'dora@autopartes.test']);
    $this->boss = Employee::factory()->reportingTo($this->director)->create(['name' => 'Beto Ruiz', 'email' => 'beto@autopartes.test']);
    $this->ana = Employee::factory()->reportingTo($this->boss)->create(['name' => 'Ana Solís', 'email' => 'ana@autopartes.test']);
});

/**
 * A request of Ana waiting for her boss, or in the state the factory names: approved, taken, rejected, cancelled, escalated.
 *
 * @param  list<string>  $states
 */
function waitingRequest(string ...$states): LeaveRequest
{
    $factory = LeaveRequest::factory();

    foreach ($states as $state) {
        $factory = $factory->{$state}();
    }

    return $factory->create(['tenant_id' => test()->tenant->id, 'employee_id' => test()->ana->id, 'approver_employee_id' => test()->boss->id]);
}

it('queues the email to the manager instead of sending it, once the request is saved', function (): void {
    Queue::fake();
    Notification::fake();

    $request = resolve(RequestLeave::class)->handle($this->ana->id, null, CarbonImmutable::parse('2026-11-09'), CarbonImmutable::parse('2026-11-20'));

    Queue::assertPushed(RequestToDecide::class, fn (RequestToDecide $job): bool => $job->requestId === $request->id && $job->tenantId === $this->tenant->id);
    Notification::assertNothingSent();
});

it('queues the email to the owner when the request is born with RH', function (): void {
    Queue::fake();

    $request = resolve(RequestLeave::class)->handle($this->director->id, null, CarbonImmutable::parse('2026-11-09'), CarbonImmutable::parse('2026-11-20'));

    Queue::assertPushed(RequestEscalated::class, fn (RequestEscalated $job): bool => $job->requestId === $request->id);
});

it('queues the email to the person once the request is answered', function (): void {
    Queue::fake();
    $request = waitingRequest();

    resolve(App\Modules\Leave\Actions\DecideRequest::class)->handle($request->id, false, App\Modules\Leave\Support\Decider::hr($this->owner->id), 'Cierre de mes');

    Queue::assertPushed(RequestDecided::class, fn (RequestDecided $job): bool => $job->requestId === $request->id);
});

it('runs after the commit and is retried twice, a minute and five minutes later', function (string $job): void {
    $instance = (new ReflectionClass($job))->newInstanceWithoutConstructor();

    expect($instance)->toBeInstanceOf(ShouldQueueAfterCommit::class)
        ->and($instance->tries)->toBe(3)
        ->and($instance->backoff)->toBe([60, 300]);
})->with([RequestToDecide::class, RequestEscalated::class, RequestDecided::class]);

describe('to the manager', function (): void {
    it('tells the manager whose it is to answer', function (): void {
        Notification::fake();
        $request = waitingRequest();

        RequestToDecide::dispatchSync($this->tenant->id, $request->id, $this->boss->id);

        Notification::assertSentOnDemandTimes(ToDecideMail::class, 1);
    });

    it('tells nobody when it was answered before the email went out', function (string $state): void {
        Notification::fake();
        $request = waitingRequest($state);

        RequestToDecide::dispatchSync($this->tenant->id, $request->id, $this->boss->id);

        Notification::assertNothingSent();
    })->with(['approved', 'rejected', 'cancelled']);

    it('tells nobody when it was given to another manager, who has their own email', function (): void {
        Notification::fake();
        $request = waitingRequest();
        $request->update(['approver_employee_id' => $this->director->id]);

        RequestToDecide::dispatchSync($this->tenant->id, $request->id, $this->boss->id);

        Notification::assertNothingSent();
    });

    it('tells nobody when it already reached RH', function (): void {
        Notification::fake();
        $request = waitingRequest('escalated');

        RequestToDecide::dispatchSync($this->tenant->id, $request->id, $this->boss->id);

        Notification::assertNothingSent();
    });

    it('does not swallow a failure: the queue retries it', function (): void {
        Notification::fake();
        $this->mock(WorkerLinks::class)->shouldReceive('to')->andThrow(new RuntimeException('no link'));
        $request = waitingRequest();

        expect(fn () => RequestToDecide::dispatchSync($this->tenant->id, $request->id, $this->boss->id))->toThrow(RuntimeException::class, 'no link');
    });

    it('tells nobody when the request is gone', function (): void {
        Notification::fake();

        RequestToDecide::dispatchSync($this->tenant->id, '00000000-0000-4000-8000-000000000000', $this->boss->id);

        Notification::assertNothingSent();
    });
});

describe('to the owner', function (): void {
    it('tells the owner a request reached RH', function (): void {
        Notification::fake();
        $request = waitingRequest('escalated');

        RequestEscalated::dispatchSync($this->tenant->id, $request->id);

        Notification::assertSentOnDemandTimes(EscalatedMail::class, 1);
    });

    it('tells nobody when it was answered or cancelled before the email went out', function (string $state): void {
        Notification::fake();
        $request = waitingRequest('escalated', $state);

        RequestEscalated::dispatchSync($this->tenant->id, $request->id);

        Notification::assertNothingSent();
    })->with(['approved', 'rejected', 'cancelled']);

    it('tells nobody when it is back with a manager', function (): void {
        Notification::fake();
        $request = waitingRequest();

        RequestEscalated::dispatchSync($this->tenant->id, $request->id);

        Notification::assertNothingSent();
    });
});

describe('to the person', function (): void {
    it('tells the person what was answered', function (string $state): void {
        Notification::fake();
        $request = waitingRequest($state);

        RequestDecided::dispatchSync($this->tenant->id, $request->id);

        Notification::assertSentOnDemandTimes(DecidedMail::class, 1);
    })->with(['approved', 'rejected', 'taken']);

    it('tells nobody what is no longer answered: it was cancelled in between', function (): void {
        Notification::fake();
        $request = waitingRequest('approved', 'cancelled');

        RequestDecided::dispatchSync($this->tenant->id, $request->id);

        Notification::assertNothingSent();
    });

    it('tells nobody when the person has no email', function (): void {
        Notification::fake();
        $this->ana->update(['email' => null]);
        $request = waitingRequest('approved');

        RequestDecided::dispatchSync($this->tenant->id, $request->id);

        Notification::assertNothingSent();
    });
});
