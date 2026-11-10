<?php

declare(strict_types=1);

use App\Modules\Leave\Actions\RouteRequests;
use App\Modules\Leave\Enums\EscalationReason;
use App\Modules\Leave\Enums\RequestStatus;
use App\Modules\Leave\Models\LeaveEntry;
use App\Modules\Leave\Models\LeaveHoliday;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Leave\Notifications\RequestEscalated;
use App\Modules\Leave\Notifications\RequestToDecide;
use App\Modules\Organization\Contracts\ReportingLines;
use App\Modules\Organization\Models\Employee;
use App\Modules\Telework\Contracts\PortalAccess;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

mutates(RouteRequests::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'autopartes']);
    $this->owner = ownerOf($this->tenant);
    LeaveSetting::factory()->create(['tenant_id' => $this->tenant->id]);
    Notification::fake();
    $this->director = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Dora Díaz', 'email' => 'dora@autopartes.test']);
    $this->boss = Employee::factory()->reportingTo($this->director)->create(['name' => 'Beto Ruiz', 'email' => 'beto@autopartes.test']);
    $this->ana = Employee::factory()->reportingTo($this->boss)->create(['name' => 'Ana Solís']);
    // Thursday 19 November 2026, 00:40 in Mexico City.
    $this->travelTo('2026-11-19 06:40:00');
});

/**
 * A request of Ana waiting for her manager, asked on Friday 13 November 2026 unless told otherwise.
 *
 * @param  array<string, mixed>  $attributes
 */
function pendingRequest(array $attributes = []): LeaveRequest
{
    return LeaveRequest::factory()->create([
        'tenant_id' => test()->tenant->id, 'employee_id' => test()->ana->id, 'approver_employee_id' => test()->boss->id,
        'starts_on' => '2026-12-07', 'ends_on' => '2026-12-11', 'working_days' => 5, 'requested_on' => '2026-11-13', ...$attributes,
    ]);
}

/**
 * @return array{taken: int, reassigned: int, escalated: int}
 */
function routeRequests(): array
{
    return resolve(RouteRequests::class)->handle();
}

/**
 * The real reporting lines, counting how many times a chain is asked and failing in one company.
 */
function watchedLines(?string $failingTenantId = null): ReportingLines
{
    return new class(resolve(ReportingLines::class), $failingTenantId) implements ReportingLines
    {
        public int $asked = 0;

        public function __construct(private readonly ReportingLines $real, private readonly ?string $failingTenantId) {}

        public function managerOf(string $employeeId): ?string
        {
            return $this->real->managerOf($employeeId);
        }

        public function chainOf(string $employeeId): array
        {
            $this->asked++;

            return resolve(CurrentTenant::class)->id() === $this->failingTenantId ? throw new RuntimeException('Broken company.') : $this->real->chainOf($employeeId);
        }

        public function directReportIds(string $employeeId): array
        {
            return $this->real->directReportIds($employeeId);
        }

        public function subtreeIds(string $employeeId): array
        {
            return $this->real->subtreeIds($employeeId);
        }
    };
}

function reloadedRequest(LeaveRequest $request): LeaveRequest
{
    return LeaveRequest::query()->withoutGlobalScopes()->whereKey($request->id)->sole();
}

/**
 * @param  class-string<Illuminate\Notifications\Notification>  $class
 * @return list<array{string, string}> the recipient and the link of each email of a kind sent so far
 */
function routedMails(string $class): array
{
    $mails = [];
    Notification::assertSentOnDemand($class, function (object $notification, array $channels, AnonymousNotifiable $notifiable) use (&$mails): bool {
        $mails[] = [$notifiable->routes['mail'], $notification->toMail($notifiable)->actionUrl];

        return true;
    });

    return $mails;
}

it('gives a manager 3 business days: asked on a Friday before a holiday Monday, it reaches RH the next Friday', function (): void {
    $request = pendingRequest();

    // Thursday 19: the third business day (Tuesday 17, Wednesday 18, Thursday 19) is not over.
    expect(routeRequests())->toBe(['taken' => 0, 'reassigned' => 0, 'escalated' => 0])
        ->and(reloadedRequest($request)->escalated_at)->toBeNull();
    Notification::assertNothingSent();

    $this->travelTo('2026-11-20 06:40:00');
    expect(routeRequests())->toBe(['taken' => 0, 'reassigned' => 0, 'escalated' => 1]);

    $escalated = reloadedRequest($request);
    expect($escalated->only(['status', 'escalation_reason', 'approver_employee_id']))->toBe(['status' => RequestStatus::Requested, 'escalation_reason' => EscalationReason::Timeout, 'approver_employee_id' => $this->boss->id])
        ->and($escalated->escalated_at?->toDateTimeString())->toBe('2026-11-20 06:40:00')
        ->and(routedMails(RequestEscalated::class))->toBe([[$this->owner->email, route('tenant.vacations.requests.index', $this->tenant)]]);
    Notification::assertSentOnDemandTimes(RequestToDecide::class, 0);
});

it('decides the day in Mexico, not in UTC', function (): void {
    $request = pendingRequest();

    // 05:30 UTC of Friday 20 is still Thursday 19 in Mexico City.
    $this->travelTo('2026-11-20 05:30:00');
    routeRequests();

    expect(reloadedRequest($request)->escalated_at)->toBeNull();
});

it('counts the business days the company configured, without its own holidays', function (): void {
    $request = pendingRequest(['requested_on' => '2026-11-17']);
    config(['leave.escalation_business_days' => 1]);

    // Asked on Tuesday 17: with 1 day the manager has Wednesday 18; on Thursday 19 it reaches RH.
    LeaveHoliday::factory()->create(['tenant_id' => $this->tenant->id, 'date' => '2026-11-18']);
    expect(routeRequests()['escalated'])->toBe(0);

    // With the 18th off, the day of the manager is Thursday 19.
    $this->travelTo('2026-11-20 06:40:00');
    expect(routeRequests()['escalated'])->toBe(1)
        ->and(reloadedRequest($request)->escalation_reason)->toBe(EscalationReason::Timeout);
});

it('does not escalate nor email twice, however many times it runs', function (): void {
    pendingRequest();
    $this->travelTo('2026-11-20 06:40:00');
    routeRequests();

    $this->travelTo('2026-11-21 06:40:00');
    expect(routeRequests())->toBe(['taken' => 0, 'reassigned' => 0, 'escalated' => 0]);
    Notification::assertSentOnDemandTimes(RequestEscalated::class, 1);
});

it('marks as taken the approved requests that ended before today, and leaves the ledger as it is', function (): void {
    $yesterday = pendingRequest(['status' => 'approved', 'decided_at' => now(), 'starts_on' => '2026-11-17', 'ends_on' => '2026-11-18', 'working_days' => 2]);
    $today = pendingRequest(['status' => 'approved', 'decided_at' => now(), 'starts_on' => '2026-11-19', 'ends_on' => '2026-11-19', 'working_days' => 1]);
    $waiting = pendingRequest(['starts_on' => '2026-11-10', 'ends_on' => '2026-11-11', 'working_days' => 2, 'requested_on' => '2026-11-18']);
    $rejected = pendingRequest(['status' => 'rejected', 'decided_at' => now(), 'rejection_reason' => 'Cierre de mes', 'starts_on' => '2026-11-02', 'ends_on' => '2026-11-03', 'working_days' => 2]);
    $cancelled = pendingRequest(['status' => 'cancelled', 'cancelled_at' => now(), 'starts_on' => '2026-11-04', 'ends_on' => '2026-11-05', 'working_days' => 2]);
    $entries = LeaveEntry::query()->withoutGlobalScopes()->count();

    expect(routeRequests())->toBe(['taken' => 1, 'reassigned' => 0, 'escalated' => 0])
        ->and(array_map(fn (LeaveRequest $request): string => reloadedRequest($request)->status->value, [$yesterday, $today, $waiting, $rejected, $cancelled]))->toBe(['taken', 'approved', 'requested', 'rejected', 'cancelled'])
        ->and(LeaveEntry::query()->withoutGlobalScopes()->count())->toBe($entries);
    Notification::assertNothingSent();

    $this->travelTo('2026-11-20 06:40:00');
    expect(routeRequests()['taken'])->toBe(1)
        ->and(reloadedRequest($today)->status)->toBe(RequestStatus::Taken);
});

it('follows a change of manager: the next run gives the request to the new one and emails them', function (): void {
    $request = pendingRequest(['requested_on' => '2026-11-18']);
    $this->ana->update(['manager_id' => $this->director->id]);

    expect(routeRequests())->toBe(['taken' => 0, 'reassigned' => 1, 'escalated' => 0]);

    workIn($this->tenant);
    expect(reloadedRequest($request)->only(['approver_employee_id', 'escalated_at']))->toBe(['approver_employee_id' => $this->director->id, 'escalated_at' => null])
        ->and(routedMails(RequestToDecide::class))->toBe([['dora@autopartes.test', url('portal/'.resolve(PortalAccess::class)->token($this->director->id).'/vacations/team')]]);

    expect(routeRequests())->toBe(['taken' => 0, 'reassigned' => 0, 'escalated' => 0]);
    Notification::assertSentOnDemandTimes(RequestToDecide::class, 1);
});

it('goes up to the first active manager when the one it waited for left, and to RH when nobody is', function (): void {
    $request = pendingRequest(['requested_on' => '2026-11-18']);
    $this->boss->update(['deactivated_at' => now()]);

    expect(routeRequests())->toBe(['taken' => 0, 'reassigned' => 1, 'escalated' => 0])
        ->and(reloadedRequest($request)->approver_employee_id)->toBe($this->director->id);

    $this->director->update(['deactivated_at' => now()]);
    expect(routeRequests())->toBe(['taken' => 0, 'reassigned' => 0, 'escalated' => 1]);

    $escalated = reloadedRequest($request);
    expect($escalated->only(['approver_employee_id', 'escalation_reason', 'status']))->toBe(['approver_employee_id' => null, 'escalation_reason' => EscalationReason::NoManager, 'status' => RequestStatus::Requested])
        ->and($escalated->escalated_at?->toDateTimeString())->toBe('2026-11-19 06:40:00');
    Notification::assertSentOnDemandTimes(RequestEscalated::class, 1);
});

it('leaves alone what is already with RH or already answered, whoever the manager is now', function (): void {
    $this->ana->update(['manager_id' => $this->director->id]);
    $escalated = LeaveRequest::factory()->escalated()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'approver_employee_id' => $this->boss->id, 'starts_on' => '2026-12-07', 'ends_on' => '2026-12-11']);
    $approved = pendingRequest(['status' => 'approved', 'decided_at' => now(), 'starts_on' => '2026-12-14', 'ends_on' => '2026-12-18']);

    expect(routeRequests())->toBe(['taken' => 0, 'reassigned' => 0, 'escalated' => 0])
        ->and(reloadedRequest($escalated)->approver_employee_id)->toBe($this->boss->id)
        ->and(reloadedRequest($approved)->approver_employee_id)->toBe($this->boss->id);
    Notification::assertNothingSent();
});

it('asks once who answers for a person, however many requests they have waiting', function (): void {
    pendingRequest(['requested_on' => '2026-11-18']);
    pendingRequest(['requested_on' => '2026-11-18', 'starts_on' => '2026-12-14', 'ends_on' => '2026-12-18']);
    $this->app->instance(ReportingLines::class, $lines = watchedLines());

    routeRequests();

    expect($lines->asked)->toBe(1);
});

it('routes only in the companies that use vacations and may be used, each on its own', function (): void {
    $this->travelTo('2026-11-20 06:40:00');
    $mine = pendingRequest();
    $unused = LeaveRequest::factory()->create(['requested_on' => '2026-11-13']);
    $blocked = Tenant::factory()->deactivated()->create();
    LeaveSetting::factory()->create(['tenant_id' => $blocked->id]);
    $ofBlocked = LeaveRequest::factory()->create(['tenant_id' => $blocked->id, 'requested_on' => '2026-11-13']);
    $other = Tenant::factory()->create();
    LeaveSetting::factory()->create(['tenant_id' => $other->id]);
    $ofOther = LeaveRequest::factory()->create(['tenant_id' => $other->id, 'requested_on' => '2026-11-13', 'approver_employee_id' => Employee::factory()->create(['tenant_id' => $other->id])->id]);

    // Mine times out; the one of the other company has an approver who is not the person's manager: it has nobody, so it goes to RH too.
    expect(routeRequests())->toBe(['taken' => 0, 'reassigned' => 0, 'escalated' => 2])
        ->and(reloadedRequest($mine)->escalation_reason)->toBe(EscalationReason::Timeout)
        ->and(reloadedRequest($ofOther)->escalation_reason)->toBe(EscalationReason::NoManager)
        ->and(reloadedRequest($unused)->escalated_at)->toBeNull()
        ->and(reloadedRequest($ofBlocked)->escalated_at)->toBeNull();
});

it('keeps routing in the other companies when one fails', function (): void {
    Exceptions::fake();
    $this->travelTo('2026-11-20 06:40:00');
    $failing = Tenant::factory()->create();
    LeaveSetting::factory()->create(['tenant_id' => $failing->id]);
    $broken = LeaveRequest::factory()->create(['tenant_id' => $failing->id, 'requested_on' => '2026-11-13']);
    $mine = pendingRequest();
    $this->app->instance(ReportingLines::class, watchedLines($failing->id));

    expect(routeRequests()['escalated'])->toBe(1)
        ->and(reloadedRequest($mine)->escalated_at)->not->toBeNull()
        ->and(reloadedRequest($broken)->escalated_at)->toBeNull();
    Exceptions::assertReported(fn (RuntimeException $failure): bool => $failure->getMessage() === 'Broken company.');
});

it('logs each change with ids, and the totals of the run', function (): void {
    $this->travelTo('2026-11-20 06:40:00');
    $timedOut = pendingRequest();
    $taken = pendingRequest(['status' => 'approved', 'decided_at' => now(), 'starts_on' => '2026-11-17', 'ends_on' => '2026-11-18', 'working_days' => 2]);
    $moved = LeaveRequest::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => Employee::factory()->reportingTo($this->director)->create()->id, 'approver_employee_id' => $this->boss->id, 'requested_on' => '2026-11-19']);
    $orphan = LeaveRequest::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->director->id, 'approver_employee_id' => $this->boss->id, 'requested_on' => '2026-11-19']);
    $lines = [];
    Log::listen(function (MessageLogged $logged) use (&$lines): void {
        $lines[] = $logged->message.' '.json_encode($logged->context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    });

    routeRequests();

    $leave = array_values(array_filter($lines, fn (string $line): bool => str_starts_with($line, 'leave.')));
    sort($leave);
    $expected = [
        'leave.request_taken '.json_encode(['request_id' => $taken->id, 'employee_id' => $this->ana->id], JSON_THROW_ON_ERROR),
        'leave.request_reassigned '.json_encode(['request_id' => $moved->id, 'approver_employee_id' => $this->director->id], JSON_THROW_ON_ERROR),
        'leave.request_escalated '.json_encode(['request_id' => $orphan->id, 'reason' => 'no_manager'], JSON_THROW_ON_ERROR),
        'leave.request_escalated '.json_encode(['request_id' => $timedOut->id, 'reason' => 'timeout'], JSON_THROW_ON_ERROR),
        'leave.requests_routed '.json_encode(['taken' => 1, 'reassigned' => 1, 'escalated' => 2], JSON_THROW_ON_ERROR),
    ];
    sort($expected);
    workIn($this->tenant);
    expect($leave)->toBe($expected)
        ->and(implode("\n", $lines))->not->toContain(resolve(PortalAccess::class)->token($this->director->id));
});

it('runs as a command and reports what it did', function (): void {
    $this->travelTo('2026-11-20 06:40:00');
    pendingRequest();
    pendingRequest(['status' => 'approved', 'decided_at' => now(), 'starts_on' => '2026-11-17', 'ends_on' => '2026-11-18', 'working_days' => 2]);

    $this->artisan('leave:route-requests')->expectsOutput('Solicitudes disfrutadas: 1, reasignadas: 0, escaladas a RH: 1')->assertSuccessful();
});
