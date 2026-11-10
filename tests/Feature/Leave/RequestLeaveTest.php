<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Leave\Actions\RequestLeave;
use App\Modules\Leave\Actions\SaveHoliday;
use App\Modules\Leave\Enums\RequestStatus;
use App\Modules\Leave\Enums\Tranche;
use App\Modules\Leave\Enums\WorkWeek;
use App\Modules\Leave\Exceptions\RequestRefused;
use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveEntry;
use App\Modules\Leave\Models\LeaveHoliday;
use App\Modules\Leave\Models\LeavePolicy;
use App\Modules\Leave\Models\LeavePolicyAssignment;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Leave\Support\AllocationLine;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

mutates(RequestLeave::class);

beforeEach(function (): void {
    workIn($this->tenant = Tenant::factory()->create());
    // Monday 2 November 2026, noon in Mexico City.
    $this->travelTo('2026-11-02 18:00:00');
    $this->ana = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
});

/**
 * A period of the person with its days granted in the ledger.
 */
function grantedPeriod(Employee $person, int $years, string $anniversary, int $statutory, int $extra = 0): LeaveEntitlement
{
    return LeaveEntitlement::factory()->withGrant()->create([
        'tenant_id' => $person->tenant_id, 'employee_id' => $person->id, 'years_of_service' => $years, 'anniversary_on' => $anniversary, 'statutory_days' => $statutory, 'extra_days' => $extra,
    ]);
}

/**
 * Asks, by default, for Monday 9 to Friday 20 November 2026: 9 working days, the 16th is a holiday.
 */
function askLeave(Employee $person, string $from = '2026-11-09', string $to = '2026-11-20', ?string $typeId = null, ?string $note = null, ?int $recordedBy = null): LeaveRequest
{
    return resolve(RequestLeave::class)->handle($person->id, $typeId, CarbonImmutable::parse($from), CarbonImmutable::parse($to), $note, $recordedBy);
}

function refusedFor(Closure $ask): ?string
{
    try {
        $ask();
    } catch (RequestRefused $refused) {
        return $refused->reason;
    }

    return null;
}

it('asks for vacations: working days of the policy, the balance of that moment and where the days would come from', function (): void {
    $period = grantedPeriod($this->ana, 2, '2026-03-15', 14);

    $request = askLeave($this->ana, note: '  Viaje familiar ')->refresh();

    expect($request->only(['tenant_id', 'employee_id', 'leave_type_id', 'working_days', 'status', 'note', 'had_balance', 'short_by', 'recorded_by', 'decided_at', 'cancelled_at']))->toBe([
        'tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'leave_type_id' => null, 'working_days' => 9, 'status' => RequestStatus::Requested, 'note' => 'Viaje familiar',
        'had_balance' => true, 'short_by' => 0, 'recorded_by' => null, 'decided_at' => null, 'cancelled_at' => null,
    ])->and([$request->starts_on->toDateString(), $request->ends_on->toDateString(), $request->requested_on->toDateString()])->toBe(['2026-11-09', '2026-11-20', '2026-11-02'])
        ->and($request->allocation)->toEqual([['entitlement_id' => $period->id, 'tranche' => 'statutory', 'days' => 9]])
        // Asking never touches the ledger.
        ->and(LeaveEntry::query()->count())->toBe(1);
});

it('takes the day it was asked from the local day, not from UTC', function (): void {
    // 05:30 UTC of the 3rd is still the 2nd in Mexico City.
    $this->travelTo('2026-11-03 05:30:00');

    expect(askLeave($this->ana, '2026-11-02', '2026-11-02')->requested_on->toDateString())->toBe('2026-11-02');
});

it('spreads the days over two periods, the oldest first, and the statutory days before the extra ones', function (): void {
    $a = grantedPeriod($this->ana, 1, '2025-03-15', 12, 2);
    LeaveEntry::factory()->of($a)->taken(9)->create();
    LeaveEntry::factory()->of($a)->taken(2, Tranche::Extra)->create();
    $b = grantedPeriod($this->ana, 2, '2026-03-15', 14);

    $request = askLeave($this->ana);

    expect($request->allocation)->toEqual([
        ['entitlement_id' => $a->id, 'tranche' => 'statutory', 'days' => 3],
        ['entitlement_id' => $b->id, 'tranche' => 'statutory', 'days' => 6],
    ])->and($request->only(['had_balance', 'short_by']))->toBe(['had_balance' => true, 'short_by' => 0]);
});

it('can be sent without the days to cover it, and says how many are missing', function (): void {
    $period = grantedPeriod($this->ana, 1, '2026-03-15', 5);

    $request = askLeave($this->ana);

    expect($request->only(['had_balance', 'short_by', 'status']))->toBe(['had_balance' => false, 'short_by' => 4, 'status' => RequestStatus::Requested])
        ->and($request->allocation)->toEqual([['entitlement_id' => $period->id, 'tranche' => 'statutory', 'days' => 5]]);
});

it('has the balance when it is exactly the days asked', function (): void {
    grantedPeriod($this->ana, 1, '2026-03-15', 9);

    expect(askLeave($this->ana)->only(['had_balance', 'short_by']))->toBe(['had_balance' => true, 'short_by' => 0]);
});

it('lets a person in their first year ask, with no days', function (): void {
    $request = askLeave($this->ana);

    expect($request->only(['had_balance', 'short_by', 'allocation']))->toBe(['had_balance' => false, 'short_by' => 9, 'allocation' => []]);
});

it('counts against the balance the days of their other vacations still waiting', function (int $waiting, bool $expected): void {
    grantedPeriod($this->ana, 2, '2026-03-15', 14);
    LeaveRequest::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'starts_on' => '2026-12-01', 'ends_on' => '2026-12-14', 'working_days' => $waiting]);

    expect(askLeave($this->ana)->had_balance)->toBe($expected);
})->with([
    '10 waiting and 9 more are over the 14' => [10, false],
    '6 waiting and 9 more are over the 14' => [6, false],
    '5 waiting and 9 more are the 14' => [5, true],
]);

it('does not count against the balance what is not a vacation waiting: leaves, decided ones and those of others', function (): void {
    grantedPeriod($this->ana, 2, '2026-03-15', 14);
    $type = LeaveType::factory()->create(['tenant_id' => $this->tenant->id]);
    $other = ['starts_on' => '2026-12-01', 'ends_on' => '2026-12-14', 'working_days' => 10];
    LeaveRequest::factory()->ofType($type)->create(['employee_id' => $this->ana->id, ...$other]);
    LeaveRequest::factory()->rejected()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, ...$other]);
    LeaveRequest::factory()->cancelled()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, ...$other]);
    // An approved one is already in the ledger: counting it here too would take it twice.
    LeaveRequest::factory()->approved()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'starts_on' => '2027-01-04', 'ends_on' => '2027-01-08', 'working_days' => 5]);
    LeaveRequest::factory()->create(['tenant_id' => $this->tenant->id, ...$other]);

    expect(askLeave($this->ana)->had_balance)->toBeTrue();
});

it('takes off the balance the days given in advance', function (): void {
    grantedPeriod($this->ana, 2, '2026-03-15', 14);
    LeaveEntry::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'leave_entitlement_id' => null, 'kind' => 'taken', 'days' => -6]);

    expect(askLeave($this->ana)->had_balance)->toBeFalse();
});

it('counts the working days with the week of the person\'s policy', function (): void {
    $policy = LeavePolicy::factory()->create(['tenant_id' => $this->tenant->id, 'work_week' => WorkWeek::MonSat]);
    LeavePolicyAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'leave_policy_id' => $policy->id]);

    // Saturday 14 counts; Monday 16 is still a holiday.
    expect(askLeave($this->ana)->working_days)->toBe(10);
});

it('leaves out the holidays of the company, and one added later does not change a request already made', function (): void {
    LeaveHoliday::factory()->create(['tenant_id' => $this->tenant->id, 'date' => '2026-11-12']);
    $request = askLeave($this->ana);
    expect($request->working_days)->toBe(8);

    resolve(SaveHoliday::class)->handle(CarbonImmutable::parse('2026-11-13'), 'Puente');

    expect($request->refresh()->working_days)->toBe(8)
        ->and(askLeave($this->ana, '2026-11-23', '2026-11-27')->working_days)->toBe(5);
});

it('refuses a range with no working days, and writes nothing', function (string $from, string $to): void {
    expect(refusedFor(fn () => askLeave($this->ana, $from, $to)))->toBe('no_working_days')
        ->and(LeaveRequest::query()->count())->toBe(0);
})->with([
    'a weekend' => ['2026-11-07', '2026-11-08'],
    'a holiday' => ['2026-11-16', '2026-11-16'],
    'ends before it starts' => ['2026-11-10', '2026-11-09'],
]);

it('refuses days that another live request of the person already holds', function (string $state, string $from, string $to, ?string $expected): void {
    LeaveRequest::factory()->{$state}()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);

    expect(refusedFor(fn () => askLeave($this->ana, $from, $to)))->toBe($expected)
        ->and(LeaveRequest::query()->count())->toBe($expected === null ? 2 : 1);
})->with([
    'the same days of an approved one' => ['approved', '2026-11-09', '2026-11-20', 'overlaps'],
    'inside one waiting' => ['escalated', '2026-11-11', '2026-11-12', 'overlaps'],
    'around one already taken' => ['taken', '2026-11-06', '2026-11-23', 'overlaps'],
    'its last day is the first of the other' => ['approved', '2026-11-05', '2026-11-09', 'overlaps'],
    'its first day is the last of the other' => ['approved', '2026-11-20', '2026-11-24', 'overlaps'],
    'the day before the other starts' => ['approved', '2026-11-05', '2026-11-06', null],
    'the day after the other ends' => ['approved', '2026-11-23', '2026-11-24', null],
    'the same days of a rejected one' => ['rejected', '2026-11-09', '2026-11-20', null],
    'the same days of a cancelled one' => ['cancelled', '2026-11-09', '2026-11-20', null],
]);

it('does not mind the same days of another person', function (): void {
    LeaveRequest::factory()->approved()->create(['tenant_id' => $this->tenant->id]);

    expect(refusedFor(fn () => askLeave($this->ana)))->toBeNull();
});

it('holds the lock of the person from before it looks for overlaps until it writes, so two at once leave one row', function (): void {
    DB::enableQueryLog();
    askLeave($this->ana);
    $queries = collect(DB::getQueryLog());
    DB::disableQueryLog();

    $lock = $queries->search(fn (array $query): bool => str_contains($query['query'], 'pg_advisory_xact_lock') && $query['bindings'] === ["leave:{$this->ana->id}"]);
    $overlap = $queries->search(fn (array $query): bool => str_contains($query['query'], 'select exists') && str_contains($query['query'], '"leave_requests"'));
    $insert = $queries->search(fn (array $query): bool => str_starts_with($query['query'], 'insert into "leave_requests"'));

    expect($lock)->toBeInt()->and($overlap)->toBeGreaterThan($lock)->and($insert)->toBeGreaterThan($overlap)
        ->and(DB::transactionLevel())->toBe(1)
        // The second of two equal requests finds the first once it gets the lock.
        ->and(refusedFor(fn () => askLeave($this->ana)))->toBe('overlaps');
});

it('refuses a person who left or who is not of the company', function (): void {
    $gone = Employee::factory()->deactivated()->create(['tenant_id' => $this->tenant->id]);
    $stranger = Employee::factory()->create();

    expect(refusedFor(fn () => askLeave($gone)))->toBe('inactive_employee')
        ->and(refusedFor(fn () => askLeave($stranger)))->toBe('inactive_employee')
        ->and(LeaveRequest::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('refuses a leave type that is archived or of another company', function (): void {
    $archived = LeaveType::factory()->archived()->create(['tenant_id' => $this->tenant->id]);
    $ofOther = LeaveType::factory()->create();

    expect(refusedFor(fn () => askLeave($this->ana, typeId: $archived->id)))->toBe('type_unavailable')
        ->and(refusedFor(fn () => askLeave($this->ana, typeId: $ofOther->id)))->toBe('type_unavailable')
        ->and(LeaveRequest::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('asks for a leave without a balance: no snapshot and no days from any period', function (): void {
    grantedPeriod($this->ana, 2, '2026-03-15', 14);
    $type = LeaveType::factory()->create(['tenant_id' => $this->tenant->id]);

    $request = askLeave($this->ana, typeId: $type->id)->refresh();

    expect($request->only(['leave_type_id', 'working_days', 'had_balance', 'allocation', 'short_by']))->toBe(['leave_type_id' => $type->id, 'working_days' => 9, 'had_balance' => null, 'allocation' => [], 'short_by' => 0]);
});

it('does not let the person ask for days already gone, but RH can record them', function (): void {
    $rh = User::factory()->create();

    expect(refusedFor(fn () => askLeave($this->ana, '2026-10-30', '2026-11-06')))->toBe('in_the_past')
        ->and(LeaveRequest::query()->count())->toBe(0)
        // Today is still theirs to ask.
        ->and(askLeave($this->ana, '2026-11-02', '2026-11-03')->working_days)->toBe(2);

    $recorded = askLeave($this->ana, '2026-10-26', '2026-10-30', recordedBy: $rh->id);
    expect($recorded->only(['working_days', 'recorded_by']))->toBe(['working_days' => 5, 'recorded_by' => $rh->id]);
});

it('refuses more than 366 days', function (): void {
    expect(refusedFor(fn () => askLeave($this->ana, '2026-11-03', '2027-11-04')))->toBe('too_long')
        ->and(LeaveRequest::query()->count())->toBe(0)
        ->and(askLeave($this->ana, '2026-11-03', '2027-11-03')->exists)->toBeTrue();
});

it('keeps a blank note as none', function (): void {
    expect(askLeave($this->ana, note: "  \n ")->note)->toBeNull();
});

it('logs the request with ids and days, never the note nor the type', function (): void {
    grantedPeriod($this->ana, 2, '2026-03-15', 14);
    $type = LeaveType::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Mudanza secreta']);
    $lines = [];
    Log::listen(function (MessageLogged $logged) use (&$lines): void {
        $lines[] = $logged->message.' '.json_encode($logged->context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    });

    $vacations = askLeave($this->ana, note: 'Nota privada');
    $leave = askLeave($this->ana, '2026-12-01', '2026-12-02', $type->id, 'Otra nota privada');

    expect($lines)->toBe([
        'leave.request_created '.json_encode(['request_id' => $vacations->id, 'employee_id' => $this->ana->id, 'working_days' => 9, 'had_balance' => true], JSON_THROW_ON_ERROR),
        // Ana has no manager: each request is born with RH (ApproverTest).
        'leave.request_escalated '.json_encode(['request_id' => $vacations->id, 'reason' => 'no_manager'], JSON_THROW_ON_ERROR),
        'leave.request_created '.json_encode(['request_id' => $leave->id, 'employee_id' => $this->ana->id, 'working_days' => 2, 'had_balance' => null], JSON_THROW_ON_ERROR),
        'leave.request_escalated '.json_encode(['request_id' => $leave->id, 'reason' => 'no_manager'], JSON_THROW_ON_ERROR),
    ])->and(implode("\n", $lines))->not->toContain('privada')->not->toContain('Mudanza')->not->toContain($type->id);
});

it('logs nothing when it refuses', function (): void {
    Log::spy();

    refusedFor(fn () => askLeave($this->ana, '2026-11-07', '2026-11-08'));

    Log::shouldNotHaveReceived('info');
});

it('previews the same numbers without writing anything', function (): void {
    $a = grantedPeriod($this->ana, 1, '2025-03-15', 12);
    LeaveEntry::factory()->of($a)->taken(9)->create();
    $b = grantedPeriod($this->ana, 2, '2026-03-15', 2);

    $preview = resolve(RequestLeave::class)->preview($this->ana->id, null, CarbonImmutable::parse('2026-11-09'), CarbonImmutable::parse('2026-11-20'));

    expect($preview)->toEqual([
        'workingDays' => 9, 'hadBalance' => false,
        'allocation' => [new AllocationLine($a->id, Tranche::Statutory, 3), new AllocationLine($b->id, Tranche::Statutory, 2)],
        'shortBy' => 4, 'continuousNotice' => true,
    ])->and(LeaveRequest::query()->count())->toBe(0);
});

it('previews a leave with no balance and no notice, and refuses what the request would refuse', function (): void {
    $type = LeaveType::factory()->create(['tenant_id' => $this->tenant->id]);
    $preview = fn (string $from, string $to, ?string $typeId = null): array => resolve(RequestLeave::class)->preview($this->ana->id, $typeId, CarbonImmutable::parse($from), CarbonImmutable::parse($to));

    expect($preview('2026-11-09', '2026-11-20', $type->id))->toBe(['workingDays' => 9, 'hadBalance' => null, 'allocation' => [], 'shortBy' => 0, 'continuousNotice' => false])
        ->and(refusedFor(fn () => $preview('2026-11-07', '2026-11-08')))->toBe('no_working_days')
        ->and(refusedFor(fn () => resolve(RequestLeave::class)->preview(Employee::factory()->deactivated()->create(['tenant_id' => $this->tenant->id])->id, null, CarbonImmutable::parse('2026-11-09'), CarbonImmutable::parse('2026-11-20'))))->toBe('inactive_employee');
});

it('reminds of the twelve days in a row while no request of the oldest period with days reaches them, and never refuses for it', function (): void {
    $a = grantedPeriod($this->ana, 1, '2025-03-15', 12);
    $b = grantedPeriod($this->ana, 2, '2026-03-15', 14);
    $notice = fn (string $from, string $to): bool => resolve(RequestLeave::class)->preview($this->ana->id, null, CarbonImmutable::parse($from), CarbonImmutable::parse($to))['continuousNotice'];

    // 11 working days, then 12.
    expect($notice('2026-11-09', '2026-11-24'))->toBeTrue()
        ->and($notice('2026-11-09', '2026-11-25'))->toBeFalse();

    // A request of 12 days in a row approved on the oldest period: nothing more to remind while that period has days.
    $long = LeaveRequest::factory()->approved()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'starts_on' => '2026-06-01', 'ends_on' => '2026-06-16', 'working_days' => 12]);
    LeaveEntry::factory()->of($a)->taken(11)->create(['leave_request_id' => $long->id]);
    LeaveEntry::factory()->of($b)->taken(1)->create(['leave_request_id' => $long->id]);
    expect($notice('2026-11-09', '2026-11-10'))->toBeFalse();

    // Once the oldest period is used up, the next one starts its own count; the long request charged it too.
    LeaveEntry::factory()->of($a)->taken(1)->create();
    expect($notice('2026-11-09', '2026-11-10'))->toBeFalse();
});

it('does not count toward the twelve days a request that was cancelled or is of another period', function (): void {
    $a = grantedPeriod($this->ana, 1, '2025-03-15', 12);
    $b = grantedPeriod($this->ana, 2, '2026-03-15', 14);
    $cancelled = LeaveRequest::factory()->cancelled()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'starts_on' => '2026-06-01', 'ends_on' => '2026-06-16', 'working_days' => 12]);
    LeaveEntry::factory()->of($a)->taken(12)->create(['leave_request_id' => $cancelled->id]);
    LeaveEntry::factory()->of($a)->create(['kind' => 'adjustment', 'days' => 12, 'leave_request_id' => $cancelled->id]);
    $onNewer = LeaveRequest::factory()->taken()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'starts_on' => '2026-07-01', 'ends_on' => '2026-07-16', 'working_days' => 12]);
    LeaveEntry::factory()->of($b)->taken(12)->create(['leave_request_id' => $onNewer->id]);

    expect(resolve(RequestLeave::class)->preview($this->ana->id, null, CarbonImmutable::parse('2026-11-09'), CarbonImmutable::parse('2026-11-10'))['continuousNotice'])->toBeTrue();
});

it('has nothing to remind a person with no days left in any period', function (): void {
    expect(resolve(RequestLeave::class)->preview($this->ana->id, null, CarbonImmutable::parse('2026-11-09'), CarbonImmutable::parse('2026-11-10'))['continuousNotice'])->toBeFalse();
});
