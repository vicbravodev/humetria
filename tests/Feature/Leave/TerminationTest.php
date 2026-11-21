<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Leave\Actions\RecordTermination;
use App\Modules\Leave\Actions\RestoreVoidedPeriods;
use App\Modules\Leave\Actions\SettleTermination;
use App\Modules\Leave\Actions\VoidLaterPeriods;
use App\Modules\Leave\Enums\EntryKind;
use App\Modules\Leave\Enums\PolicyScope;
use App\Modules\Leave\Enums\Tranche;
use App\Modules\Leave\Exceptions\AlreadyDecided;
use App\Modules\Leave\Listeners\VoidTerminationOnReactivation;
use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveEntry;
use App\Modules\Leave\Models\LeavePolicy;
use App\Modules\Leave\Models\LeavePolicyAssignment;
use App\Modules\Leave\Models\LeavePolicyRow;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Leave\Models\LeaveStatement;
use App\Modules\Leave\Models\LeaveTermination;
use App\Modules\Leave\Support\Balances;
use App\Modules\Leave\Support\PeriodBalance;
use App\Modules\Leave\Support\PeriodWindow;
use App\Modules\Organization\Actions\DeactivateEmployee;
use App\Modules\Organization\Actions\ReactivateEmployee;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

mutates(RecordTermination::class, SettleTermination::class, RestoreVoidedPeriods::class, VoidLaterPeriods::class, VoidTerminationOnReactivation::class);

beforeEach(function (): void {
    workIn($this->tenant = Tenant::factory()->create());
    LeaveSetting::factory()->create(['tenant_id' => $this->tenant->id, 'tracking_since' => '2024-01-01']);
    $this->user = User::factory()->create();
    // Wednesday 2 July 2025, noon in Mexico City.
    $this->travelTo('2025-07-02 18:00:00');
});

function hiredOn(string $on, array $attributes = []): Employee
{
    return Employee::factory()->create(['tenant_id' => test()->tenant->id, 'hired_on' => $on, ...$attributes]);
}

function terminate(Employee $person): ?LeaveTermination
{
    resolve(DeactivateEmployee::class)->handle($person);

    return LeaveTermination::query()->where('employee_id', $person->id)->whereNull('voided_at')->orderByDesc('terminated_on')->first();
}

/**
 * A period of the person with its days granted and some of them already enjoyed.
 */
function periodWith(Employee $person, int $years, string $anniversary, int $statutory, int $extra = 0, int $taken = 0): LeaveEntitlement
{
    $period = LeaveEntitlement::factory()->withGrant()->create([
        'tenant_id' => $person->tenant_id, 'employee_id' => $person->id, 'years_of_service' => $years, 'anniversary_on' => $anniversary, 'statutory_days' => $statutory, 'extra_days' => $extra,
    ]);

    if ($taken > 0) {
        LeaveEntry::factory()->of($period)->taken($taken)->create();
    }

    return $period;
}

/**
 * @return list<array{int, int}> what is left of each period of the person: statutory and extra
 */
function periodsLeftOf(Employee $person): array
{
    return array_map(fn (PeriodBalance $period): array => [$period->statutoryLeft, $period->extraLeft], Balances::of([$person->id])[$person->id] ?? []);
}

it('counts, in days only, the part of the first year of who leaves before completing it', function (): void {
    $ana = hiredOn('2025-01-01');
    Log::spy();

    $termination = terminate($ana);

    // 12 days × 182 days elapsed / 365.
    expect($termination?->only(['tenant_id', 'employee_id', 'pending_days', 'proportional_days', 'settled_at', 'settled_by', 'voided_at']))->toBe([
        'tenant_id' => $this->tenant->id, 'employee_id' => $ana->id, 'pending_days' => 0, 'proportional_days' => 5.98, 'settled_at' => null, 'settled_by' => null, 'voided_at' => null,
    ])->and($termination?->terminated_on->toDateString())->toBe('2025-07-02');
    Log::shouldHaveReceived('info')->with('leave.termination_recorded', ['termination_id' => $termination?->id, 'employee_id' => $ana->id, 'pending_days' => 0, 'proportional_days' => 5.98])->once();
});

it('counts the days to the effective date of the departure, not to the day it was typed', function (): void {
    $ana = hiredOn('2025-01-01');

    resolve(DeactivateEmployee::class)->handle($ana, effectiveOn: CarbonImmutable::parse('2025-06-11')); // 21 days before "today"
    $termination = LeaveTermination::query()->where('employee_id', $ana->id)->sole();

    // 12 days × 161 days elapsed / 365, not the 182 of today.
    expect($termination->terminated_on->toDateString())->toBe('2025-06-11')
        ->and($termination->proportional_days)->toBe(5.29);
});

it('voids with an entry of the ledger the period granted after the effective date, so only the proportional of that year is owed', function (): void {
    // Typed on 10 March 2026 for a departure on 15 February: the sixth anniversary (1 March) never happened for them.
    $this->travelTo('2026-03-10 18:00:00');
    Context::add('user_id', $this->user->id);
    $ana = hiredOn('2020-03-01');
    $fifth = periodWith($ana, 5, '2025-03-01', statutory: 20, taken: 8);
    $sixth = periodWith($ana, 6, '2026-03-01', statutory: 22);
    Log::spy();

    resolve(DeactivateEmployee::class)->handle($ana, effectiveOn: CarbonImmutable::parse('2026-02-15'));
    $termination = LeaveTermination::query()->where('employee_id', $ana->id)->sole();

    // The 12 left of the fifth year; 22 × 351 days from 1 March 2025 to 15 February 2026 / 365 for the sixth, in course.
    expect($termination->only(['pending_days', 'proportional_days']))->toBe(['pending_days' => 12, 'proportional_days' => 21.16])
        ->and(periodsLeftOf($ana))->toBe([[12, 0], [0, 0]])
        ->and(LeaveEntry::query()->where('kind', EntryKind::Adjustment)->get()->map(fn (LeaveEntry $entry): array => [$entry->leave_entitlement_id, $entry->tranche, $entry->days, $entry->recorded_by, $entry->note])->all())
        ->toBe([[$sixth->id, Tranche::Statutory, -22, $this->user->id, 'Periodo posterior a la fecha de la baja (15/02/2026): no llegó a cumplirse.']])
        // Nothing is erased: the grant is still there.
        ->and(LeaveEntry::query()->where('leave_entitlement_id', $sixth->id)->count())->toBe(2)
        ->and(AuditEntry::query()->where('event', 'leave.entitlements_voided')->sole()->metadata)->toEqual(['employee_id' => $ana->id, 'periods' => 1, 'days' => 22]);
    Log::shouldHaveReceived('info')->with('leave.entitlements_voided', ['employee_id' => $ana->id, 'periods' => 1, 'days' => 22])->once();
    expect($fifth->refresh()->id)->toBe($fifth->id);
});

it('voids the extra days of a later period too, and the period that falls on the same day of the departure stays', function (): void {
    $this->travelTo('2026-03-10 18:00:00');
    Context::add('user_id', $this->user->id);
    $ana = hiredOn('2020-03-01');
    periodWith($ana, 5, '2025-03-01', statutory: 20);
    periodWith($ana, 6, '2026-02-15', statutory: 22, extra: 3);
    $after = periodWith($ana, 7, '2026-02-16', statutory: 22, extra: 3);

    resolve(DeactivateEmployee::class)->handle($ana, effectiveOn: CarbonImmutable::parse('2026-02-15'));

    expect(periodsLeftOf($ana))->toBe([[20, 0], [22, 3], [0, 0]])
        ->and(LeaveEntry::query()->where('kind', EntryKind::Adjustment)->orderBy('days')->get()->map(fn (LeaveEntry $entry): array => [$entry->leave_entitlement_id, $entry->tranche, $entry->days])->all())
        ->toBe([[$after->id, Tranche::Statutory, -22], [$after->id, Tranche::Extra, -3]]);
});

it('does not let a departure go through when a later period would be voided with nobody to answer for it', function (): void {
    $this->travelTo('2026-03-10 18:00:00');
    $ana = hiredOn('2020-03-01');
    periodWith($ana, 6, '2026-03-01', statutory: 22);

    expect(fn () => resolve(DeactivateEmployee::class)->handle($ana, effectiveOn: CarbonImmutable::parse('2026-02-15')))->toThrow(LogicException::class)
        ->and($ana->refresh()->deactivated_at)->toBeNull()
        ->and(LeaveEntry::query()->where('kind', EntryKind::Adjustment)->count())->toBe(0);
});

it('does not count as enjoyed a vacation approved after the effective date', function (): void {
    $ana = hiredOn('2024-01-01');
    $period = periodWith($ana, 1, '2025-01-01', statutory: 12);
    $after = LeaveRequest::factory()->approved()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $ana->id, 'approver_employee_id' => null, 'starts_on' => '2025-06-23', 'ends_on' => '2025-06-27', 'working_days' => 5]);
    LeaveEntry::factory()->of($period)->taken(5)->create(['leave_request_id' => $after->id]);

    resolve(DeactivateEmployee::class)->handle($ana, effectiveOn: CarbonImmutable::parse('2025-06-11'));

    expect($after->refresh()->status)->toBe(App\Modules\Leave\Enums\RequestStatus::Cancelled)
        ->and(LeaveTermination::query()->where('employee_id', $ana->id)->sole()->pending_days)->toBe(12);
});

it('adds what was left of earlier periods and counts the year in course from the last anniversary', function (): void {
    $ana = hiredOn('2024-01-01');
    periodWith($ana, 1, '2025-01-01', statutory: 12, taken: 5);

    // 7 left of the first year; the second gives 14: 14 × 182 / 365.
    expect(terminate($ana)?->only(['pending_days', 'proportional_days']))->toBe(['pending_days' => 7, 'proportional_days' => 6.98]);
});

it('does not count the period granted at the last anniversary twice: it is the year completed, the proportional is the year in course', function (): void {
    $beatriz = hiredOn('2022-01-01');
    periodWith($beatriz, 3, '2025-01-01', statutory: 16);

    // 16 days of the completed third year are owed whole; the fourth (18) only for the 182 days elapsed: 18 × 182 / 365.
    expect(terminate($beatriz)?->only(['pending_days', 'proportional_days']))->toBe(['pending_days' => 16, 'proportional_days' => 8.98]);
});

it('counts the seniority the company recognizes, the days of the person\'s policy and what they were given in advance', function (): void {
    $policy = LeavePolicy::factory()->create(['tenant_id' => $this->tenant->id]);
    LeavePolicyRow::factory()->create(['tenant_id' => $this->tenant->id, 'leave_policy_id' => $policy->id, 'year_from' => 1, 'year_to' => 5, 'days' => 20]);
    LeavePolicyRow::factory()->fixed()->create(['tenant_id' => $this->tenant->id, 'leave_policy_id' => $policy->id]);
    LeavePolicyAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'leave_policy_id' => $policy->id, 'scope' => PolicyScope::Default]);
    $ana = hiredOn('2025-06-01', ['seniority_on' => '2025-01-01']);
    LeaveEntry::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $ana->id, 'leave_entitlement_id' => null, 'kind' => EntryKind::Taken, 'days' => -3, 'authorized_by' => $this->user->id]);

    // 21 days a year (20 of the tier and the birthday) × 182 / 365; 3 days were given in advance.
    expect(terminate($ana)?->only(['pending_days', 'proportional_days']))->toBe(['pending_days' => -3, 'proportional_days' => 10.47]);
});

it('counts the day they leave as people in Mexico live it, and nothing for the day of an anniversary', function (): void {
    // 05:30 UTC of 2 January 2026 is still 1 January in Mexico City: the first anniversary.
    $this->travelTo('2026-01-02 05:30:00');
    $termination = terminate(hiredOn('2025-01-01'));

    expect($termination?->terminated_on->toDateString())->toBe('2026-01-01')
        ->and($termination?->proportional_days)->toBe(0.0);
});

it('counts nothing proportional for who has not started yet', function (): void {
    expect(terminate(hiredOn('2025-08-01'))?->only(['pending_days', 'proportional_days']))->toBe(['pending_days' => 0, 'proportional_days' => 0.0]);
});

it('records nothing for who has no start date, nor in a company that does not use Vacaciones', function (): void {
    expect(terminate(hiredOn('2025-01-01', ['hired_on' => null])))->toBeNull();

    $other = Tenant::factory()->create();
    workIn($other);
    resolve(DeactivateEmployee::class)->handle(Employee::factory()->create(['tenant_id' => $other->id, 'hired_on' => '2025-01-01']));

    expect(LeaveTermination::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('settles the days once: every period ends at zero with termination entries, audited', function (): void {
    $ana = hiredOn('2023-01-01');
    $first = periodWith($ana, 1, '2024-01-01', statutory: 12, extra: 3, taken: 12);
    $second = periodWith($ana, 2, '2025-01-01', statutory: 14, extra: 2, taken: 4);
    $termination = terminate($ana);
    $this->travelTo('2025-07-04 16:00:00');
    Log::spy();

    $settled = resolve(SettleTermination::class)->handle($termination?->id ?? '', $this->user->id);

    expect($settled->only(['id', 'pending_days', 'settled_by']))->toBe(['id' => $termination?->id, 'pending_days' => 15, 'settled_by' => $this->user->id])
        ->and($settled->settled_at?->toDateTimeString())->toBe('2025-07-04 16:00:00')
        ->and(periodsLeftOf($ana))->toBe([[0, 0], [0, 0]])
        ->and(LeaveEntry::query()->where('kind', EntryKind::Termination)->orderBy('days')->get()->map(fn (LeaveEntry $entry): array => [$entry->leave_entitlement_id, $entry->tranche, $entry->days, $entry->recorded_by])->all())->toBe([
            [$second->id, Tranche::Statutory, -10, $this->user->id],
            [$first->id, Tranche::Extra, -3, $this->user->id],
            [$second->id, Tranche::Extra, -2, $this->user->id],
        ])
        ->and(AuditEntry::query()->where('event', 'leave.termination_settled')->sole()->metadata)->toEqual(['employee_id' => $ana->id, 'days' => 15]);
    Log::shouldHaveReceived('info')->with('leave.termination_settled', ['termination_id' => $termination?->id, 'employee_id' => $ana->id, 'days' => 15])->once();

    $entries = LeaveEntry::query()->count();
    expect(fn () => resolve(SettleTermination::class)->handle($termination?->id ?? '', $this->user->id))->toThrow(AlreadyDecided::class)
        ->and(LeaveEntry::query()->count())->toBe($entries)
        ->and(AuditEntry::query()->where('event', 'leave.termination_settled')->count())->toBe(1);
});

it('does not settle the termination of a person who works here: their days are theirs again', function (): void {
    $ana = hiredOn('2024-01-01');
    periodWith($ana, 1, '2025-01-01', statutory: 12);
    $termination = terminate($ana);
    // Back at work without the event that voids the termination (reactivated by hand, in the database).
    $ana->update(['deactivated_at' => null, 'terminated_on' => null, 'deactivation_reason' => null]);

    expect(fn () => resolve(SettleTermination::class)->handle($termination?->id ?? '', $this->user->id))->toThrow(AlreadyDecided::class)
        ->and(LeaveEntry::query()->where('kind', EntryKind::Termination)->count())->toBe(0)
        ->and($termination?->refresh()->settled_at)->toBeNull()
        ->and(Balances::available($ana->id))->toBe(12);
});

it('warns when the balance of today is not the one recorded with the departure, and settles all the same', function (): void {
    $ana = hiredOn('2024-01-01');
    $period = periodWith($ana, 1, '2025-01-01', statutory: 12);
    $termination = terminate($ana);
    // Two days were corrected after the departure was recorded.
    LeaveEntry::factory()->of($period)->create(['kind' => EntryKind::Adjustment, 'days' => 2, 'note' => 'Antigüedad reconocida', 'recorded_by' => $this->user->id]);
    Log::spy();

    $settled = resolve(SettleTermination::class)->handle($termination?->id ?? '', $this->user->id);

    expect($settled->settled_at)->not->toBeNull()
        ->and(periodsLeftOf($ana))->toBe([[0, 0]])
        ->and(AuditEntry::query()->where('event', 'leave.termination_settled')->sole()->metadata)->toEqual(['employee_id' => $ana->id, 'days' => 14, 'recorded_days' => 12]);
    Log::shouldHaveReceived('warning')->with('leave.termination_settled_with_difference', ['termination_id' => $termination?->id, 'employee_id' => $ana->id, 'recorded_days' => 12, 'days_today' => 14])->once();
});

it('does not warn when the balance of today is the recorded one', function (): void {
    $ana = hiredOn('2024-01-01');
    periodWith($ana, 1, '2025-01-01', statutory: 12);
    $termination = terminate($ana);
    Log::spy();

    resolve(SettleTermination::class)->handle($termination?->id ?? '', $this->user->id);

    Log::shouldNotHaveReceived('warning');
});

/**
 * The four first years of someone hired on 1 March 2020, all enjoyed: nothing left of them and nothing for the nightly grant to give.
 */
function enjoyedFirstYears(Employee $person): void
{
    foreach ([[1, 12], [2, 14], [3, 16], [4, 18]] as [$year, $days]) {
        periodWith($person, $year, (2020 + $year).'-03-01', statutory: $days, taken: $days);
    }
}

it('gives back the periods voided by a departure that is undone, with entries that say why, and as many times as it happens', function (): void {
    $this->travelTo('2026-03-10 18:00:00');
    Context::add('user_id', $this->user->id);
    $ana = hiredOn('2020-03-01');
    enjoyedFirstYears($ana);
    periodWith($ana, 5, '2025-03-01', statutory: 20, taken: 8);
    $sixth = periodWith($ana, 6, '2026-03-01', statutory: 22, extra: 2);

    $done = array_fill(0, 4, [0, 0]);
    Log::spy();

    foreach ([1, 2] as $round) {
        resolve(DeactivateEmployee::class)->handle($ana, effectiveOn: CarbonImmutable::parse('2026-02-15'));
        expect(periodsLeftOf($ana))->toBe([...$done, [12, 0], [0, 0]]);

        resolve(ReactivateEmployee::class)->handle($ana->refresh());

        // As it was before the mistaken departure: nothing was lost.
        expect(periodsLeftOf($ana))->toBe([...$done, [12, 0], [22, 2]])
            ->and(LeaveEntry::query()->where('kind', EntryKind::Adjustment)->where('days', '>', 0)->count())->toBe($round * 2)
            ->and(AuditEntry::query()->where('event', 'leave.entitlements_restored')->count())->toBe($round);
        Log::shouldHaveReceived('info')->with('leave.entitlements_restored', ['employee_id' => $ana->id, 'periods' => 1, 'days' => 24])->times($round);
    }

    expect(LeaveEntry::query()->where('leave_entitlement_id', $sixth->id)->where('kind', EntryKind::Adjustment)->where('days', '>', 0)->get()->every(fn (LeaveEntry $entry): bool => $entry->recorded_by === $this->user->id && str_starts_with((string) $entry->note, 'Baja deshecha')))->toBeTrue()
        ->and(AuditEntry::query()->where('event', 'leave.entitlements_restored')->first()?->metadata)->toEqual(['employee_id' => $ana->id, 'periods' => 1, 'days' => 24]);
});

it('does not give back what was settled: the settled departure stays as it is', function (): void {
    $this->travelTo('2026-03-10 18:00:00');
    Context::add('user_id', $this->user->id);
    $ana = hiredOn('2020-03-01');
    enjoyedFirstYears($ana);
    periodWith($ana, 5, '2025-03-01', statutory: 20, taken: 8);
    periodWith($ana, 6, '2026-03-01', statutory: 22);
    $termination = terminate($ana) ?? throw new LogicException('Nothing was recorded.');
    resolve(SettleTermination::class)->handle($termination->id, $this->user->id);

    resolve(ReactivateEmployee::class)->handle($ana->refresh());

    expect(periodsLeftOf($ana))->toBe([...array_fill(0, 4, [0, 0]), [0, 0], [0, 0]])
        ->and(AuditEntry::query()->where('event', 'leave.entitlements_restored')->count())->toBe(0);
});

it('voids a termination not yet settled when the person comes back, and never settles a voided one', function (): void {
    $ana = hiredOn('2024-01-01');
    periodWith($ana, 1, '2025-01-01', statutory: 12);
    $termination = terminate($ana);
    $this->travelTo('2025-07-10 16:00:00');
    Log::spy();

    resolve(ReactivateEmployee::class)->handle($ana);

    expect($termination?->refresh()->voided_at?->toDateTimeString())->toBe('2025-07-10 16:00:00')
        ->and(fn () => resolve(SettleTermination::class)->handle($termination?->id ?? '', $this->user->id))->toThrow(AlreadyDecided::class)
        ->and(periodsLeftOf($ana))->toBe([[12, 0]]);
    Log::shouldHaveReceived('info')->with('leave.termination_voided', ['employee_id' => $ana->id, 'terminations' => 1])->once();
});

it('keeps a settled termination when the person comes back, and records another when they leave again', function (): void {
    $ana = hiredOn('2024-01-01');
    $first = terminate($ana);
    resolve(SettleTermination::class)->handle($first?->id ?? '', $this->user->id);
    Log::spy();

    resolve(ReactivateEmployee::class)->handle($ana);
    Log::shouldNotHaveReceived('info', ['leave.termination_voided', Mockery::any()]);
    $this->travelTo('2025-09-01 18:00:00');
    $second = terminate($ana->refresh());

    expect($first?->refresh()->voided_at)->toBeNull()
        ->and($second?->id)->not->toBe($first?->id)
        ->and($second?->terminated_on->toDateString())->toBe('2025-09-01');
});

it('replaces a termination left open by a new one, never two open for a person', function (): void {
    $ana = hiredOn('2024-01-01');
    $first = terminate($ana);
    $this->travelTo('2025-07-05 18:00:00');

    $second = resolve(RecordTermination::class)->handle($ana->id, PeriodWindow::today());

    expect($first?->refresh()->voided_at?->toDateTimeString())->toBe('2025-07-05 18:00:00')
        ->and($second?->voided_at)->toBeNull()
        ->and(fn () => LeaveTermination::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $ana->id]))->toThrow(QueryException::class);
});

it('erases nothing of the file of who leaves', function (): void {
    $ana = hiredOn('2024-01-01');
    $period = periodWith($ana, 1, '2025-01-01', statutory: 12, taken: 5);
    LeaveStatement::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $ana->id, 'leave_entitlement_id' => $period->id]);
    LeaveRequest::factory()->taken()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $ana->id, 'starts_on' => '2025-03-03', 'ends_on' => '2025-03-07', 'working_days' => 5]);

    $termination = terminate($ana);
    resolve(SettleTermination::class)->handle($termination?->id ?? '', $this->user->id);

    expect([LeaveEntitlement::query()->count(), LeaveStatement::query()->count(), LeaveRequest::query()->count()])->toBe([1, 1, 1])
        // The grant, what was enjoyed and what the termination took: three entries, none removed.
        ->and(LeaveEntry::query()->orderBy('created_at')->pluck('days')->sort()->values()->all())->toBe([-7, -5, 12]);
});

it('has no amount of money anywhere: days, dates and who settled', function (): void {
    expect(Schema::getColumnListing('leave_terminations'))->toBe(['id', 'tenant_id', 'employee_id', 'terminated_on', 'pending_days', 'proportional_days', 'settled_at', 'settled_by', 'voided_at']);
});

it('does not read, change nor settle the termination of another company', function (): void {
    $other = Tenant::factory()->create();
    $theirs = LeaveTermination::factory()->create(['tenant_id' => $other->id]);

    expect(LeaveTermination::query()->count())->toBe(0)
        ->and(LeaveTermination::query()->find($theirs->id))->toBeNull()
        ->and(LeaveTermination::query()->whereKey($theirs->id)->update(['voided_at' => now()]))->toBe(0)
        ->and(fn () => resolve(SettleTermination::class)->handle($theirs->id, $this->user->id))->toThrow(ModelNotFoundException::class);

    workIn($other);
    expect(LeaveTermination::query()->sole()->only(['settled_at', 'voided_at']))->toBe(['settled_at' => null, 'voided_at' => null]);
});
