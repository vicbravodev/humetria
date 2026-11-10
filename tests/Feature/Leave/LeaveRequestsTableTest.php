<?php

declare(strict_types=1);

use App\Modules\Leave\Enums\EscalationReason;
use App\Modules\Leave\Enums\RequestStatus;
use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveEntry;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Inside a savepoint: a refused write must not end the test's transaction.
 */
function refusedByDatabase(Closure $write, string $constraint): void
{
    expect(fn () => DB::transaction($write))->toThrow(QueryException::class, $constraint);
}

it('names each status in Spanish and tells which ones hold the dates', function (): void {
    expect(array_map(fn (RequestStatus $status): array => [$status->value, $status->label()], RequestStatus::cases()))->toBe([
        ['requested', 'Solicitada'], ['approved', 'Aprobada'], ['rejected', 'Rechazada'], ['cancelled', 'Cancelada'], ['taken', 'Disfrutada'],
    ])->and(RequestStatus::live())->toBe([RequestStatus::Requested, RequestStatus::Approved, RequestStatus::Taken])
        ->and(array_map(fn (EscalationReason $reason): string => $reason->value, EscalationReason::cases()))->toBe(['no_manager', 'timeout']);
});

it('keeps a vacation request as it was made, with its dates as days', function (): void {
    $request = LeaveRequest::factory()->create(['allocation' => [['entitlement_id' => 'p', 'tranche' => 'statutory', 'days' => 9]]])->refresh();

    expect($request->only(['leave_type_id', 'working_days', 'status', 'had_balance', 'short_by', 'approver_employee_id', 'escalated_at', 'escalation_reason', 'decided_at', 'cancelled_at', 'recorded_by']))->toBe([
        'leave_type_id' => null, 'working_days' => 9, 'status' => RequestStatus::Requested, 'had_balance' => true, 'short_by' => 0, 'approver_employee_id' => null,
        'escalated_at' => null, 'escalation_reason' => null, 'decided_at' => null, 'cancelled_at' => null, 'recorded_by' => null,
    ])->and([$request->starts_on->toDateString(), $request->ends_on->toDateString(), $request->requested_on->toDateString()])->toBe(['2026-11-09', '2026-11-20', '2026-11-02'])
        ->and($request->allocation)->toEqual([['entitlement_id' => 'p', 'tranche' => 'statutory', 'days' => 9]]);
});

it('builds each state of a request', function (): void {
    $tenant = Tenant::factory()->create();
    $type = LeaveType::factory()->create(['tenant_id' => $tenant->id]);

    expect(LeaveRequest::factory()->approved()->create()->refresh()->only(['status']))->toBe(['status' => RequestStatus::Approved])
        ->and(LeaveRequest::factory()->taken()->create()->refresh()->decided_at)->not->toBeNull()
        ->and(LeaveRequest::factory()->rejected()->create()->refresh()->only(['status', 'rejection_reason']))->toBe(['status' => RequestStatus::Rejected, 'rejection_reason' => 'Cierre de mes'])
        ->and(LeaveRequest::factory()->cancelled()->create()->refresh()->cancelled_at)->not->toBeNull()
        ->and(LeaveRequest::factory()->escalated()->create()->refresh()->escalation_reason)->toBe(EscalationReason::Timeout)
        ->and(LeaveRequest::factory()->ofType($type)->create()->refresh()->only(['tenant_id', 'leave_type_id', 'had_balance']))->toBe(['tenant_id' => $tenant->id, 'leave_type_id' => $type->id, 'had_balance' => null]);
});

it('rejects in the database a request that breaks its own rules', function (array $attributes, string $constraint): void {
    // By SQL, past the casts of the model.
    $request = LeaveRequest::factory()->create();

    refusedByDatabase(fn () => DB::table('leave_requests')->where('id', $request->id)->update($attributes), $constraint);
})->with([
    'ends before it starts' => [['starts_on' => '2026-11-10', 'ends_on' => '2026-11-09'], 'leave_requests_ends_after_it_starts'],
    'no working days' => [['working_days' => 0], 'leave_requests_working_days_positive'],
    'unknown status' => [['status' => 'pending'], 'leave_requests_status_known'],
    'negative shortfall' => [['short_by' => -1], 'leave_requests_short_by_not_negative'],
    'vacations without the balance snapshot' => [['had_balance' => null], 'leave_requests_balance_only_for_vacations'],
    'escalated without a reason' => [['escalated_at' => '2026-11-05 12:00:00'], 'leave_requests_escalation_whole'],
    'unknown escalation reason' => [['escalated_at' => '2026-11-05 12:00:00', 'escalation_reason' => 'bored'], 'leave_requests_escalation_reason_known'],
    'decided without when' => [['status' => 'approved'], 'leave_requests_decided_when'],
    'taken without when it was approved' => [['status' => 'taken'], 'leave_requests_decided_when'],
    'rejected without a reason' => [['status' => 'rejected', 'decided_at' => '2026-11-05 12:00:00'], 'leave_requests_rejection_explained'],
    'rejected with a blank reason' => [['status' => 'rejected', 'decided_at' => '2026-11-05 12:00:00', 'rejection_reason' => '  '], 'leave_requests_rejection_explained'],
    'cancelled without when' => [['status' => 'cancelled'], 'leave_requests_cancelled_when'],
]);

it('rejects in the database a leave with a balance snapshot', function (): void {
    $type = LeaveType::factory()->create();

    refusedByDatabase(fn () => LeaveRequest::factory()->ofType($type)->create(['had_balance' => true]), 'leave_requests_balance_only_for_vacations');
});

it('rejects in the database a type, an approver or a decider of another company', function (string $column, string $constraint): void {
    $ofOther = $column === 'leave_type_id' ? LeaveType::factory()->create() : Employee::factory()->create();

    refusedByDatabase(fn () => LeaveRequest::factory()->create([$column => $ofOther->id, ...($column === 'leave_type_id' ? ['had_balance' => null] : [])]), $constraint);
})->with([
    ['leave_type_id', 'leave_requests_leave_type_id_tenant_id_foreign'],
    ['approver_employee_id', 'leave_requests_approver_employee_id_tenant_id_foreign'],
    ['decided_by_employee_id', 'leave_requests_decided_by_employee_id_tenant_id_foreign'],
]);

it('ties an entry of the ledger to a request of the same person only', function (): void {
    $request = LeaveRequest::factory()->create();
    $period = LeaveEntitlement::factory()->create(['tenant_id' => $request->tenant_id, 'employee_id' => $request->employee_id]);
    $ofColleague = LeaveRequest::factory()->create(['tenant_id' => $request->tenant_id]);

    expect(LeaveEntry::factory()->of($period)->taken(3)->create(['leave_request_id' => $request->id])->exists)->toBeTrue();
    refusedByDatabase(fn () => LeaveEntry::factory()->of($period)->taken(3)->create(['leave_request_id' => $ofColleague->id]), 'leave_entries_request_of_the_person');
});

it('charges a request once to each tranche of a period, and once in advance', function (): void {
    $request = LeaveRequest::factory()->create();
    $period = LeaveEntitlement::factory()->create(['tenant_id' => $request->tenant_id, 'employee_id' => $request->employee_id, 'extra_days' => 2]);
    $charge = fn (array $attributes = []) => LeaveEntry::factory()->of($period)->taken(1)->create(['leave_request_id' => $request->id, ...$attributes]);

    $charge();
    $charge(['tranche' => 'extra']);
    $charge(['leave_entitlement_id' => null]);
    refusedByDatabase(fn () => $charge(), 'leave_entries_one_charge_per_request');
    refusedByDatabase(fn () => $charge(['leave_entitlement_id' => null]), 'leave_entries_one_charge_per_request');

    // Charges with no request (opening corrections) are not limited.
    expect(LeaveEntry::factory()->of($period)->taken(1)->count(2)->create())->toHaveCount(2);
});

it('takes in the database the days a cancelled request gives back, with no note of RH', function (): void {
    $request = LeaveRequest::factory()->create();
    $period = LeaveEntitlement::factory()->create(['tenant_id' => $request->tenant_id, 'employee_id' => $request->employee_id]);

    expect(LeaveEntry::factory()->of($period)->create(['kind' => 'adjustment', 'days' => 3, 'leave_request_id' => $request->id])->exists)->toBeTrue();
    refusedByDatabase(fn () => LeaveEntry::factory()->of($period)->create(['kind' => 'adjustment', 'days' => 3]), 'leave_entries_adjustment_explained');
});

it('does not read or change the requests of another company with a query', function (): void {
    $a = Tenant::factory()->create();
    $ofB = LeaveRequest::factory()->create();

    workIn($a);
    expect(LeaveRequest::query()->count())->toBe(0)
        ->and(LeaveRequest::query()->find($ofB->id))->toBeNull()
        ->and(LeaveRequest::query()->whereKey($ofB->id)->update(['working_days' => 1]))->toBe(0);

    workIn(tenantOf($ofB->tenant_id));
    expect(LeaveRequest::query()->sole()->working_days)->toBe(9);
});
