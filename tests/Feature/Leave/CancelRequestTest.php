<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Leave\Actions\CancelRequest;
use App\Modules\Leave\Enums\EntryKind;
use App\Modules\Leave\Enums\RequestStatus;
use App\Modules\Leave\Enums\Tranche;
use App\Modules\Leave\Exceptions\AlreadyDecided;
use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveEntry;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Support\Balances;
use App\Modules\Leave\Support\Decider;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

mutates(CancelRequest::class, Decider::class);

beforeEach(function (): void {
    workIn($this->tenant = Tenant::factory()->create());
    // Monday 2 November 2026, noon in Mexico City.
    $this->travelTo('2026-11-02 18:00:00');
    $this->ana = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->rh = User::factory()->create();
    // 12 statutory and 2 extra in the older period, 14 in the newer one.
    $this->a = LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'years_of_service' => 1, 'anniversary_on' => '2025-03-15', 'statutory_days' => 12, 'extra_days' => 2]);
    $this->b = LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'years_of_service' => 2, 'anniversary_on' => '2026-03-15', 'statutory_days' => 14]);
});

/**
 * A request of Ana in a state, from Monday 9 to Friday 20 November 2026 unless told otherwise.
 *
 * @param  array<string, mixed>  $attributes
 */
function anasRequest(string $state, array $attributes = []): LeaveRequest
{
    $factory = LeaveRequest::factory();

    return ($state === 'requested' ? $factory : $factory->{$state}())->create(['tenant_id' => test()->tenant->id, 'employee_id' => test()->ana->id, ...$attributes]);
}

/**
 * Charges an approved request of Ana to the ledger: 12 statutory and 2 extra of the older period, 3 of the newer one and
 * 1 in advance.
 */
function chargeToLedger(LeaveRequest $request): void
{
    LeaveEntry::factory()->of(test()->a)->taken(12)->create(['leave_request_id' => $request->id]);
    LeaveEntry::factory()->of(test()->a)->taken(2, Tranche::Extra)->create(['leave_request_id' => $request->id]);
    LeaveEntry::factory()->of(test()->b)->taken(3)->create(['leave_request_id' => $request->id]);
    LeaveEntry::factory()->create(['tenant_id' => $request->tenant_id, 'employee_id' => $request->employee_id, 'leave_entitlement_id' => null, 'kind' => 'taken', 'days' => -1, 'leave_request_id' => $request->id]);
}

/**
 * @return array{list<list<int>>, int} statutory and extra left of each period, and the days in advance
 */
function anasBalance(): array
{
    $id = test()->ana->id;

    return [array_map(fn ($period): array => [$period->statutoryLeft, $period->extraLeft], Balances::of([$id])[$id]), Balances::advanced([$id])[$id] ?? 0];
}

function cancelAs(Decider $by, LeaveRequest $request): LeaveRequest
{
    return resolve(CancelRequest::class)->handle($request->id, $by);
}

it('says who decides: a manager, the person who asked, or RH', function (): void {
    expect(get_object_vars(Decider::manager('m')))->toBe(['employeeId' => 'm', 'userId' => null, 'isHr' => false])
        ->and(get_object_vars(Decider::requester('p')))->toBe(['employeeId' => 'p', 'userId' => null, 'isHr' => false])
        ->and(get_object_vars(Decider::hr(7)))->toBe(['employeeId' => null, 'userId' => 7, 'isHr' => true]);
});

it('cancels a request still waiting without touching the ledger', function (): void {
    $request = anasRequest('requested');

    $cancelled = cancelAs(Decider::requester($this->ana->id), $request);

    expect($cancelled->id)->toBe($request->id)
        ->and($request->refresh()->status)->toBe(RequestStatus::Cancelled)
        ->and($request->cancelled_at?->toDateTimeString())->toBe('2026-11-02 18:00:00')
        ->and(LeaveEntry::query()->count())->toBe(3)
        ->and(AuditEntry::query()->count())->toBe(0);
});

it('gives the days of an approved request that has not started back to the same periods and tranches', function (): void {
    $request = anasRequest('approved', ['starts_on' => '2026-11-03']);
    chargeToLedger($request);
    expect(anasBalance())->toBe([[[0, 0], [11, 0]], -1]);

    cancelAs(Decider::requester($this->ana->id), $request);

    $returned = LeaveEntry::query()->where('kind', EntryKind::Adjustment)->orderBy('days')->get()->map(fn (LeaveEntry $entry): array => [$entry->leave_entitlement_id, $entry->tranche, $entry->days, $entry->leave_request_id, $entry->recorded_by])->all();
    expect($request->refresh()->status)->toBe(RequestStatus::Cancelled)
        ->and($returned)->toBe([
            [null, Tranche::Statutory, 1, $request->id, null],
            [$this->a->id, Tranche::Extra, 2, $request->id, null],
            [$this->b->id, Tranche::Statutory, 3, $request->id, null],
            [$this->a->id, Tranche::Statutory, 12, $request->id, null],
        ])
        ->and(anasBalance())->toBe([[[12, 2], [14, 0]], 0]);
});

it('does not give back the charges of another request, nor what is not a charge', function (): void {
    $other = anasRequest('taken', ['starts_on' => '2026-06-01', 'ends_on' => '2026-06-05', 'working_days' => 5]);
    LeaveEntry::factory()->of($this->b)->taken(5)->create(['leave_request_id' => $other->id]);
    $request = anasRequest('approved');
    LeaveEntry::factory()->of($this->b)->taken(9)->create(['leave_request_id' => $request->id]);

    cancelAs(Decider::hr($this->rh->id), $request);

    expect(anasBalance())->toBe([[[12, 2], [9, 0]], 0])
        ->and(LeaveEntry::query()->where('kind', EntryKind::Adjustment)->count())->toBe(1);
});

it('does not let the person cancel an approved request from the day it starts, but RH can', function (string $state, string $startsOn): void {
    $request = anasRequest($state, ['starts_on' => $startsOn]);
    chargeToLedger($request);

    expect(fn () => cancelAs(Decider::requester($this->ana->id), $request))->toThrow(AlreadyDecided::class, 'Esta solicitud ya fue respondida.')
        ->and($request->refresh()->status->value)->toBe($state)
        ->and(LeaveEntry::query()->where('kind', EntryKind::Adjustment)->count())->toBe(0);

    cancelAs(Decider::hr($this->rh->id), $request);

    expect($request->refresh()->status)->toBe(RequestStatus::Cancelled)
        ->and(LeaveEntry::query()->where('kind', EntryKind::Adjustment)->where('recorded_by', $this->rh->id)->count())->toBe(4)
        ->and(anasBalance())->toBe([[[12, 2], [14, 0]], 0]);
})->with([
    'it starts today' => ['approved', '2026-11-02'],
    'it started last week' => ['approved', '2026-10-26'],
    'it was already enjoyed' => ['taken', '2026-10-19'],
]);

it('decides what today is by the local day', function (): void {
    // 05:30 UTC of the 3rd is still the 2nd in Mexico City: a request that starts on the 3rd has not started.
    $this->travelTo('2026-11-03 05:30:00');
    $request = anasRequest('approved', ['starts_on' => '2026-11-03']);

    expect(cancelAs(Decider::requester($this->ana->id), $request)->status)->toBe(RequestStatus::Cancelled);
});

it('cancels once: a second time changes nothing', function (): void {
    $request = anasRequest('approved');
    chargeToLedger($request);
    cancelAs(Decider::requester($this->ana->id), $request);

    expect(fn () => cancelAs(Decider::requester($this->ana->id), $request))->toThrow(AlreadyDecided::class)
        ->and(fn () => cancelAs(Decider::hr($this->rh->id), $request))->toThrow(AlreadyDecided::class)
        ->and(LeaveEntry::query()->where('kind', EntryKind::Adjustment)->count())->toBe(4)
        ->and(anasBalance())->toBe([[[12, 2], [14, 0]], 0]);
});

it('has nothing to cancel in a rejected request', function (): void {
    $request = anasRequest('rejected');

    expect(fn () => cancelAs(Decider::requester($this->ana->id), $request))->toThrow(AlreadyDecided::class)
        ->and(fn () => cancelAs(Decider::hr($this->rh->id), $request))->toThrow(AlreadyDecided::class)
        ->and($request->refresh()->status)->toBe(RequestStatus::Rejected);
});

it('does not find the request of another person, of their team or of another company', function (): void {
    $boss = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
    $request = anasRequest('requested', ['approver_employee_id' => $boss->id]);
    $ofOther = LeaveRequest::factory()->create();

    expect(fn () => cancelAs(Decider::requester($boss->id), $request))->toThrow(ModelNotFoundException::class)
        ->and(fn () => cancelAs(Decider::manager($boss->id), $request))->toThrow(ModelNotFoundException::class)
        ->and(fn () => cancelAs(Decider::hr($this->rh->id), $ofOther))->toThrow(ModelNotFoundException::class)
        ->and(fn () => cancelAs(Decider::requester($ofOther->employee_id), $ofOther))->toThrow(ModelNotFoundException::class)
        ->and($request->refresh()->status)->toBe(RequestStatus::Requested)
        ->and(LeaveRequest::query()->withoutGlobalScopes()->whereKey($ofOther->id)->sole()->status)->toBe(RequestStatus::Requested);
});

it('holds the lock of the person while it reads the request and writes', function (): void {
    $request = anasRequest('approved');
    chargeToLedger($request);

    DB::enableQueryLog();
    cancelAs(Decider::requester($this->ana->id), $request);
    $queries = collect(DB::getQueryLog());
    DB::disableQueryLog();

    $lock = $queries->search(fn (array $query): bool => str_contains($query['query'], 'pg_advisory_xact_lock') && $query['bindings'] === ["leave:{$this->ana->id}"]);
    $read = $queries->search(fn (array $query): bool => str_contains($query['query'], 'from "leave_requests"') && str_ends_with($query['query'], 'for update'));
    $insert = $queries->search(fn (array $query): bool => str_starts_with($query['query'], 'insert into "leave_entries"'));
    $update = $queries->search(fn (array $query): bool => str_starts_with($query['query'], 'update "leave_requests"'));

    expect($lock)->toBeInt()->and($read)->toBeGreaterThan($lock)->and($insert)->toBeGreaterThan($read)->and($update)->toBeGreaterThan($read);
});

it('logs every cancellation, and audits the ones of RH', function (): void {
    $mine = anasRequest('requested');
    $corrected = anasRequest('taken', ['starts_on' => '2026-10-19', 'ends_on' => '2026-10-23', 'working_days' => 5, 'note' => 'Nota privada']);
    LeaveEntry::factory()->of($this->b)->taken(5)->create(['leave_request_id' => $corrected->id]);
    $lines = [];
    Log::listen(function (MessageLogged $logged) use (&$lines): void {
        $lines[] = $logged->message.' '.json_encode($logged->context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    });

    cancelAs(Decider::requester($this->ana->id), $mine);
    cancelAs(Decider::hr($this->rh->id), $corrected);

    $audit = AuditEntry::query()->where('event', 'leave.request_cancelled')->sole();
    expect($lines)->toBe([
        'leave.request_cancelled '.json_encode(['request_id' => $mine->id, 'employee_id' => $this->ana->id, 'by_hr' => false, 'days_returned' => 0], JSON_THROW_ON_ERROR),
        'leave.request_cancelled '.json_encode(['request_id' => $corrected->id, 'employee_id' => $this->ana->id, 'by_hr' => true, 'days_returned' => 5], JSON_THROW_ON_ERROR),
    ])->and($audit->subject_id)->toBe($corrected->id)
        ->and($audit->metadata)->toEqual(['employee_id' => $this->ana->id, 'was' => 'taken', 'days_returned' => 5]);
});

it('writes nothing when there is nothing to cancel', function (): void {
    $request = anasRequest('cancelled');
    Log::spy();

    expect(fn () => cancelAs(Decider::hr($this->rh->id), $request))->toThrow(AlreadyDecided::class);

    Log::shouldNotHaveReceived('info');
    expect(AuditEntry::query()->count())->toBe(0);
});
