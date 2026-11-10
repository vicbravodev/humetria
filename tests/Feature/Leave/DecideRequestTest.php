<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Leave\Actions\DecideRequest;
use App\Modules\Leave\Enums\EntryKind;
use App\Modules\Leave\Enums\RequestStatus;
use App\Modules\Leave\Enums\Tranche;
use App\Modules\Leave\Exceptions\AlreadyDecided;
use App\Modules\Leave\Exceptions\RequestRefused;
use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveEntry;
use App\Modules\Leave\Models\LeavePolicy;
use App\Modules\Leave\Models\LeavePolicyAssignment;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Leave\Notifications\RequestDecided;
use App\Modules\Leave\Support\Balances;
use App\Modules\Leave\Support\Decider;
use App\Modules\Organization\Models\Employee;
use App\Modules\Telework\Contracts\PortalAccess;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

mutates(DecideRequest::class);

beforeEach(function (): void {
    workIn($this->tenant = Tenant::factory()->create());
    $this->travelTo('2026-11-02 18:00:00');
    Notification::fake();
    $this->boss = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Beto Ruiz']);
    $this->ana = Employee::factory()->reportingTo($this->boss)->create(['name' => 'Ana Solís', 'email' => 'ana@autopartes.test']);
    $this->rh = User::factory()->create();
    // 3 days left of the older period and 14 of the newer one.
    $this->a = LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'years_of_service' => 1, 'anniversary_on' => '2025-03-15', 'statutory_days' => 12]);
    LeaveEntry::factory()->of($this->a)->taken(9)->create();
    $this->b = LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'years_of_service' => 2, 'anniversary_on' => '2026-03-15', 'statutory_days' => 14]);
});

/**
 * A request of Ana waiting for her manager: 9 working days unless told otherwise.
 *
 * @param  array<string, mixed>  $attributes
 */
function waiting(array $attributes = []): LeaveRequest
{
    return LeaveRequest::factory()->create(['tenant_id' => test()->tenant->id, 'employee_id' => test()->ana->id, 'approver_employee_id' => test()->boss->id, 'note' => 'Nota privada', ...$attributes]);
}

function asBoss(): Decider
{
    return Decider::manager(test()->boss->id);
}

function asRh(): Decider
{
    return Decider::hr(test()->rh->id);
}

function decide(LeaveRequest $request, bool $approve, Decider $by, ?string $reason = null): LeaveRequest
{
    return resolve(DecideRequest::class)->handle($request->id, $approve, $by, $reason);
}

/**
 * @return list<array{?string, Tranche, int}> period, tranche and days of what a request charged, by period
 */
function chargesOf(LeaveRequest $request): array
{
    return LeaveEntry::query()->where('leave_request_id', $request->id)->where('kind', EntryKind::Taken)->get()
        ->map(fn (LeaveEntry $entry): array => [$entry->leave_entitlement_id, $entry->tranche, $entry->days])->sortBy(fn (array $charge): int => $charge[2])->values()->all();
}

/**
 * @return list<int> the days left of each period of Ana, from the oldest
 */
function leftByPeriod(): array
{
    return array_map(fn ($period): int => $period->left(), Balances::of([test()->ana->id])[test()->ana->id] ?? []);
}

function allowAdvances(): void
{
    $policy = LeavePolicy::factory()->create(['tenant_id' => test()->tenant->id, 'allows_advance' => true]);
    LeavePolicyAssignment::factory()->create(['tenant_id' => test()->tenant->id, 'leave_policy_id' => $policy->id]);
}

/**
 * @return list<MailMessage> the answers emailed so far, as they would be rendered
 */
function answersSent(): array
{
    $mails = [];
    Notification::assertSentOnDemand(RequestDecided::class, function (RequestDecided $notification, array $channels, AnonymousNotifiable $notifiable) use (&$mails): bool {
        expect($channels)->toBe(['mail'])->and($notifiable->routes['mail'])->toBe('ana@autopartes.test');
        $mails[] = $notification->toMail($notifiable);

        return true;
    });

    return $mails;
}

it('approves: charges the oldest period first and keeps where the days came from', function (): void {
    $request = waiting();

    $approved = decide($request, true, asBoss())->refresh();

    expect($approved->only(['id', 'status', 'short_by', 'decided_by_employee_id', 'decided_by_user_id', 'rejection_reason']))->toBe([
        'id' => $request->id, 'status' => RequestStatus::Approved, 'short_by' => 0, 'decided_by_employee_id' => $this->boss->id, 'decided_by_user_id' => null, 'rejection_reason' => null,
    ])->and($approved->decided_at?->toDateTimeString())->toBe('2026-11-02 18:00:00')
        ->and($approved->allocation)->toEqual([['entitlement_id' => $this->a->id, 'tranche' => 'statutory', 'days' => 3], ['entitlement_id' => $this->b->id, 'tranche' => 'statutory', 'days' => 6]])
        ->and(chargesOf($request))->toBe([[$this->b->id, Tranche::Statutory, -6], [$this->a->id, Tranche::Statutory, -3]])
        ->and(leftByPeriod())->toBe([0, 8])
        ->and(LeaveEntry::query()->where('leave_request_id', $request->id)->whereNotNull('authorized_by')->count())->toBe(0)
        ->and(AuditEntry::query()->count())->toBe(0);
});

it('uses the statutory days of a period before its extra ones', function (): void {
    $c = LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $this->tenant->id, 'employee_id' => Employee::factory()->reportingTo($this->boss)->create()->id, 'statutory_days' => 2, 'extra_days' => 3]);
    $request = LeaveRequest::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $c->employee_id, 'approver_employee_id' => $this->boss->id, 'working_days' => 3]);

    decide($request, true, asBoss());

    expect(chargesOf($request))->toBe([[$c->id, Tranche::Statutory, -2], [$c->id, Tranche::Extra, -1]]);
});

it('uses the balance of the moment it is approved, not the one of when it was asked', function (): void {
    // When it was asked the newer period covered it all; meanwhile 8 of its days were used.
    $request = waiting(['allocation' => [['entitlement_id' => $this->b->id, 'tranche' => 'statutory', 'days' => 9]]]);
    LeaveEntry::factory()->of($this->b)->taken(8)->create();

    $approved = decide($request, true, asBoss())->refresh();

    expect(chargesOf($request))->toBe([[$this->b->id, Tranche::Statutory, -6], [$this->a->id, Tranche::Statutory, -3]])
        ->and($approved->allocation)->toEqual([['entitlement_id' => $this->a->id, 'tranche' => 'statutory', 'days' => 3], ['entitlement_id' => $this->b->id, 'tranche' => 'statutory', 'days' => 6]])
        ->and(leftByPeriod())->toBe([0, 0]);
});

it('rejects with a reason and leaves the ledger alone', function (): void {
    $request = waiting();

    $rejected = decide($request, false, asBoss(), '  Cierre de mes  ')->refresh();

    expect($rejected->only(['status', 'rejection_reason', 'decided_by_employee_id']))->toBe(['status' => RequestStatus::Rejected, 'rejection_reason' => 'Cierre de mes', 'decided_by_employee_id' => $this->boss->id])
        ->and($rejected->decided_at?->toDateTimeString())->toBe('2026-11-02 18:00:00')
        ->and(chargesOf($request))->toBe([])
        ->and(leftByPeriod())->toBe([3, 14]);
});

it('asks for a reason of 5 to 500 characters to reject', function (?string $reason, bool $valid): void {
    $request = waiting();
    $errors = [];

    try {
        decide($request, false, asBoss(), $reason);
    } catch (ValidationException $refused) {
        $errors = $refused->errors();
    }

    expect($errors)->toBe($valid ? [] : ['reason' => ['Explique el motivo del rechazo, entre 5 y 500 caracteres.']])
        ->and($request->refresh()->status)->toBe($valid ? RequestStatus::Rejected : RequestStatus::Requested);
})->with([
    'none' => [null, false],
    'blank' => ['      ', false],
    '4 characters' => [' áéíó ', false],
    '5 characters' => ['áéíóú', true],
    '500 characters' => [str_repeat('ñ', 500), true],
    '501 characters' => [str_repeat('ñ', 501), false],
]);

it('needs no reason to approve, and keeps none', function (): void {
    expect(decide(waiting(), true, asBoss(), 'Sin problema')->refresh()->rejection_reason)->toBeNull();
});

it('answers once: the second answer changes nothing', function (bool $first, bool $second): void {
    $request = waiting();
    decide($request, $first, asBoss(), 'Cierre de mes');
    $charges = chargesOf($request);
    Notification::fake();

    expect(fn () => decide($request, $second, asRh(), 'Otro motivo'))->toThrow(AlreadyDecided::class, 'Esta solicitud ya fue respondida.')
        ->and(fn () => decide($request, $second, asBoss(), 'Otro motivo'))->toThrow(AlreadyDecided::class)
        ->and($request->refresh()->only(['status', 'decided_by_user_id', 'rejection_reason']))->toBe(['status' => $first ? RequestStatus::Approved : RequestStatus::Rejected, 'decided_by_user_id' => null, 'rejection_reason' => $first ? null : 'Cierre de mes'])
        ->and(chargesOf($request))->toBe($charges)->toHaveCount($first ? 2 : 0)
        ->and(AuditEntry::query()->count())->toBe(0);
    Notification::assertNothingSent();
})->with([
    'approved, then approved again' => [true, true],
    'approved, then rejected' => [true, false],
    'rejected, then approved' => [false, true],
]);

it('has nothing to answer in a request that was cancelled or already enjoyed', function (string $state): void {
    $request = LeaveRequest::factory()->{$state}()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'approver_employee_id' => $this->boss->id]);

    expect(fn () => decide($request, true, asBoss()))->toThrow(AlreadyDecided::class)
        ->and($request->refresh()->status->value)->toBe($state)
        ->and(chargesOf($request))->toBe([]);
})->with(['cancelled', 'taken']);

it('holds the lock of the person and the row while it reads the balance and writes, so of two answers at once one wins', function (): void {
    $request = waiting();

    DB::enableQueryLog();
    decide($request, true, asRh());
    $queries = collect(DB::getQueryLog());
    DB::disableQueryLog();

    $lock = $queries->search(fn (array $query): bool => str_contains($query['query'], 'pg_advisory_xact_lock') && $query['bindings'] === ["leave:{$this->ana->id}"]);
    $row = $queries->search(fn (array $query): bool => str_contains($query['query'], 'from "leave_requests"') && str_ends_with($query['query'], 'for update'));
    $balance = $queries->search(fn (array $query): bool => str_contains($query['query'], 'sum('));
    $charge = $queries->search(fn (array $query): bool => str_starts_with($query['query'], 'insert into "leave_entries"'));
    $update = $queries->search(fn (array $query): bool => str_starts_with($query['query'], 'update "leave_requests"'));

    expect($lock)->toBeInt()->and($row)->toBeGreaterThan($lock)->and($balance)->toBeGreaterThan($row)->and($charge)->toBeGreaterThan($balance)->and($update)->toBeGreaterThan($row);
});

it('does not find a request that is not theirs to answer', function (): void {
    $request = waiting();
    $colleague = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
    $ofOther = LeaveRequest::factory()->create();

    expect(fn () => decide($request, true, Decider::manager($colleague->id)))->toThrow(ModelNotFoundException::class)
        // Nobody approves their own.
        ->and(fn () => decide($request, true, Decider::requester($this->ana->id)))->toThrow(ModelNotFoundException::class)
        ->and(fn () => decide($ofOther, true, asRh()))->toThrow(ModelNotFoundException::class)
        ->and(fn () => decide($ofOther, false, asBoss(), 'Cierre de mes'))->toThrow(ModelNotFoundException::class)
        ->and($request->refresh()->status)->toBe(RequestStatus::Requested)
        ->and(LeaveEntry::query()->withoutGlobalScopes()->where('kind', EntryKind::Taken)->whereNotNull('leave_request_id')->count())->toBe(0);
});

it('lets the manager still answer after it reached RH for taking too long', function (): void {
    $request = LeaveRequest::factory()->escalated()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'approver_employee_id' => $this->boss->id]);

    expect(decide($request, true, asBoss())->refresh()->only(['status', 'decided_by_employee_id']))->toBe(['status' => RequestStatus::Approved, 'decided_by_employee_id' => $this->boss->id]);
});

it('lets RH answer any request, with who did it and in the audit trail', function (): void {
    $noBoss = waiting(['approver_employee_id' => null, 'escalated_at' => now(), 'escalation_reason' => 'no_manager']);
    $early = waiting(['starts_on' => '2026-12-01', 'ends_on' => '2026-12-02', 'working_days' => 2]);

    decide($noBoss, true, asRh());
    decide($early, false, asRh(), 'Motivo privado');

    expect($noBoss->refresh()->only(['status', 'decided_by_employee_id', 'decided_by_user_id']))->toBe(['status' => RequestStatus::Approved, 'decided_by_employee_id' => null, 'decided_by_user_id' => $this->rh->id])
        ->and($early->refresh()->only(['status', 'decided_by_user_id']))->toBe(['status' => RequestStatus::Rejected, 'decided_by_user_id' => $this->rh->id])
        ->and(AuditEntry::query()->where('event', 'leave.request_decided_by_hr')->orderBy('id')->get()->map(fn (AuditEntry $entry): array => [$entry->subject_id, $entry->metadata])->all())->toEqual([
            [$noBoss->id, ['employee_id' => $this->ana->id, 'approved' => true, 'working_days' => 9]],
            [$early->id, ['employee_id' => $this->ana->id, 'approved' => false, 'working_days' => 2]],
        ]);
});

it('does not approve without the days when the policy gives none in advance', function (): void {
    $request = waiting(['starts_on' => '2026-11-02', 'ends_on' => '2026-11-27', 'working_days' => 19]);
    Log::spy();

    expect(fn () => decide($request, true, asBoss()))->toThrow(function (RequestRefused $refused): void {
        expect($refused->reason)->toBe('insufficient_balance')
            ->and($refused->getMessage())->toBe('No tiene días disponibles y su política no permite adelantar vacaciones.');
    })
        ->and($request->refresh()->only(['status', 'decided_at', 'short_by']))->toBe(['status' => RequestStatus::Requested, 'decided_at' => null, 'short_by' => 0])
        ->and(chargesOf($request))->toBe([])
        ->and(leftByPeriod())->toBe([3, 14])
        ->and(AuditEntry::query()->count())->toBe(0);
    Log::shouldNotHaveReceived('info');
    Notification::assertNothingSent();
});

it('approves with exactly the days the person has, without advancing any', function (): void {
    $request = waiting(['starts_on' => '2026-11-02', 'ends_on' => '2026-11-25', 'working_days' => 17]);

    decide($request, true, asBoss());

    expect(leftByPeriod())->toBe([0, 0])->and(Balances::advanced([$this->ana->id]))->toBe([]);
});

it('advances the missing days when the policy allows it, with who authorized them and in the audit trail', function (): void {
    allowAdvances();
    $request = waiting(['starts_on' => '2026-11-02', 'ends_on' => '2026-11-27', 'working_days' => 19, 'had_balance' => false]);

    $approved = decide($request, true, asRh())->refresh();

    $advance = LeaveEntry::query()->where('leave_request_id', $request->id)->whereNull('leave_entitlement_id')->sole();
    $audit = AuditEntry::query()->where('event', 'leave.advance_authorized')->sole();
    expect($approved->only(['status', 'short_by']))->toBe(['status' => RequestStatus::Approved, 'short_by' => 2])
        ->and($advance->only(['kind', 'tranche', 'days', 'authorized_by', 'recorded_by']))->toBe(['kind' => EntryKind::Taken, 'tranche' => Tranche::Statutory, 'days' => -2, 'authorized_by' => $this->rh->id, 'recorded_by' => null])
        ->and(chargesOf($request))->toBe([[$this->b->id, Tranche::Statutory, -14], [$this->a->id, Tranche::Statutory, -3], [null, Tranche::Statutory, -2]])
        ->and(leftByPeriod())->toBe([0, 0])
        ->and(Balances::advanced([$this->ana->id]))->toBe([$this->ana->id => -2])
        ->and(Balances::available($this->ana->id))->toBe(-2)
        ->and($audit->subject_id)->toBe($request->id)
        ->and($audit->metadata)->toEqual(['employee_id' => $this->ana->id, 'days' => 2, 'authorized_by_employee_id' => null, 'authorized_by_user_id' => $this->rh->id]);
});

it('advances every day to a person with no period yet, and says which manager authorized it', function (): void {
    allowAdvances();
    $new = Employee::factory()->reportingTo($this->boss)->create();
    $request = LeaveRequest::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $new->id, 'approver_employee_id' => $this->boss->id, 'working_days' => 3, 'had_balance' => false]);

    $approved = decide($request, true, asBoss())->refresh();

    expect($approved->only(['short_by', 'allocation', 'decided_by_employee_id']))->toBe(['short_by' => 3, 'allocation' => [], 'decided_by_employee_id' => $this->boss->id])
        ->and(chargesOf($request))->toBe([[null, Tranche::Statutory, -3]])
        ->and(Balances::advanced([$new->id]))->toBe([$new->id => -3])
        ->and(AuditEntry::query()->where('event', 'leave.advance_authorized')->sole()->metadata)->toEqual(['employee_id' => $new->id, 'days' => 3, 'authorized_by_employee_id' => $this->boss->id, 'authorized_by_user_id' => null]);
});

it('approves a leave without touching any balance, whatever the person has', function (): void {
    $type = LeaveType::factory()->create(['tenant_id' => $this->tenant->id]);
    $request = LeaveRequest::factory()->ofType($type)->create(['employee_id' => $this->ana->id, 'approver_employee_id' => $this->boss->id, 'starts_on' => '2026-11-02', 'ends_on' => '2027-02-26', 'working_days' => 80]);

    expect(decide($request, true, asBoss())->refresh()->only(['status', 'had_balance', 'short_by', 'allocation']))->toBe(['status' => RequestStatus::Approved, 'had_balance' => null, 'short_by' => 0, 'allocation' => []])
        ->and(chargesOf($request))->toBe([])
        ->and(leftByPeriod())->toBe([3, 14]);
});

it('emails the answer to who asked, with the link of their own portal', function (): void {
    decide(waiting(), true, asBoss());

    [$mail] = answersSent();
    expect($mail->subject)->toBe('Su solicitud fue aprobada')
        ->and($mail->greeting)->toBe('Hola, Ana Solís')
        ->and($mail->introLines)->toBe(['Su solicitud de vacaciones del 9 de noviembre de 2026 al 20 de noviembre de 2026: 9 días laborables fue aprobada.'])
        ->and($mail->actionText)->toBe('Ver mis vacaciones')
        ->and($mail->actionUrl)->toBe(url('portal/'.resolve(PortalAccess::class)->token($this->ana->id).'/vacations'));
    Notification::assertSentOnDemandTimes(RequestDecided::class, 1);
});

it('emails the reason of a rejection, and never which leave it was nor any amount', function (): void {
    $type = LeaveType::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Maternidad']);
    $request = LeaveRequest::factory()->ofType($type)->create(['employee_id' => $this->ana->id, 'approver_employee_id' => $this->boss->id, 'note' => 'Nota privada']);

    decide($request, false, asBoss(), 'Falta el certificado médico');

    [$mail] = answersSent();
    $html = (string) $mail->render();
    expect($mail->subject)->toBe('Su solicitud fue rechazada')
        ->and($mail->introLines)->toBe(['Su solicitud de un permiso del 9 de noviembre de 2026 al 20 de noviembre de 2026: 9 días laborables fue rechazada.', 'Motivo: Falta el certificado médico'])
        ->and($html)->not->toContain('Maternidad')->not->toContain('Nota privada')->not->toContain('$')->not->toContain('MXN');
});

it('emails nobody when who asked has no email or the email cannot be made, and the answer stays', function (): void {
    $this->ana->update(['email' => null]);
    decide(waiting(), true, asBoss());
    Notification::assertNothingSent();

    $this->ana->update(['email' => 'ana@autopartes.test']);
    Exceptions::fake();
    $this->mock(PortalAccess::class)->shouldReceive('token')->andThrow(new RuntimeException('no link'));
    $second = waiting(['starts_on' => '2026-12-01', 'ends_on' => '2026-12-02', 'working_days' => 2]);

    expect(decide($second, true, asBoss())->refresh()->status)->toBe(RequestStatus::Approved);
    Exceptions::assertReported(RuntimeException::class);
    Notification::assertNothingSent();
});

it('logs each answer with ids and days: never the reason, the note nor a portal token', function (): void {
    $approved = waiting();
    $rejected = waiting(['starts_on' => '2026-12-01', 'ends_on' => '2026-12-02', 'working_days' => 2]);
    $lines = [];
    Log::listen(function (MessageLogged $logged) use (&$lines): void {
        $lines[] = $logged->message.' '.json_encode($logged->context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    });

    decide($approved, true, asBoss());
    decide($rejected, false, asRh(), 'Motivo privado');

    $all = implode("\n", [...$lines, json_encode(AuditEntry::query()->get()->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
    expect(array_values(array_filter($lines, fn (string $line): bool => str_starts_with($line, 'leave.'))))->toBe([
        'leave.request_approved '.json_encode(['request_id' => $approved->id, 'employee_id' => $this->ana->id, 'working_days' => 9, 'by_hr' => false, 'advanced' => 0], JSON_THROW_ON_ERROR),
        'leave.request_rejected '.json_encode(['request_id' => $rejected->id, 'employee_id' => $this->ana->id, 'working_days' => 2, 'by_hr' => true], JSON_THROW_ON_ERROR),
    ])->and($all)->not->toContain('privad')->not->toContain(resolve(PortalAccess::class)->token($this->ana->id));
});
