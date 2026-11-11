<?php

declare(strict_types=1);

use App\Modules\Leave\Actions\ExpireExtraDays;
use App\Modules\Leave\Actions\GrantEntitlement;
use App\Modules\Leave\Actions\GrantEntitlements;
use App\Modules\Leave\Enums\EntryKind;
use App\Modules\Leave\Enums\Tranche;
use App\Modules\Leave\Enums\WorkWeek;
use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveEntry;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Leave\Support\Balances;
use App\Modules\Leave\Support\PolicyTerms;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

mutates(ExpireExtraDays::class, GrantEntitlement::class);

beforeEach(function (): void {
    workIn($this->tenant = Tenant::factory()->create());
    LeaveSetting::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->ana = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'hired_on' => '2024-03-15']);
    // 18:00 UTC is noon in Mexico City.
    $this->travelTo('2026-07-01 18:00:00');
});

/**
 * @param  array<string, mixed>  $attributes
 */
function periodWithExtra(Employee $person, int $extra, array $attributes = []): LeaveEntitlement
{
    return LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $person->tenant_id, 'employee_id' => $person->id, 'extra_days' => $extra, 'extra_expires_on' => '2026-06-30', ...$attributes]);
}

/**
 * @return list<array{string|null, Tranche, int}> the period, the tranche and the days of each expiry, oldest period first
 */
function expiries(): array
{
    return array_values(LeaveEntry::query()->withoutGlobalScopes()->where('kind', EntryKind::Expired)->orderBy('created_at')->orderBy('days')->get()
        ->map(fn (LeaveEntry $entry): array => [$entry->leave_entitlement_id, $entry->tranche, $entry->days])->all());
}

function cappedTerms(?int $cap): PolicyTerms
{
    return new PolicyTerms(null, 'Con tope', 25, WorkWeek::MonFri, false, null, $cap, [], 5);
}

it('takes off the extra days of a period the day after they expire, once', function (): void {
    $period = periodWithExtra($this->ana, 3);

    $this->travelTo('2026-06-30 18:00:00');
    expect(resolve(ExpireExtraDays::class)->handle())->toBe(0)
        ->and(expiries())->toBe([]);

    $this->travelTo('2026-07-01 18:00:00');
    expect(resolve(ExpireExtraDays::class)->handle())->toBe(1)
        ->and(expiries())->toBe([[$period->id, Tranche::Extra, -3]])
        ->and(LeaveEntry::query()->where('kind', EntryKind::Expired)->sole()->only(['tenant_id', 'employee_id', 'recorded_by', 'note']))
        ->toBe(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'recorded_by' => null, 'note' => null]);

    expect(resolve(ExpireExtraDays::class)->handle())->toBe(0)
        ->and(expiries())->toHaveCount(1);
});

it('decides the day in Mexico, not in UTC', function (): void {
    periodWithExtra($this->ana, 3);

    // 05:30 UTC of 1 July is still 30 June in Mexico City.
    $this->travelTo('2026-07-01 05:30:00');
    expect(resolve(ExpireExtraDays::class)->handle())->toBe(0);
});

it('takes off only the extra days that are left', function (): void {
    $period = periodWithExtra($this->ana, 3);
    LeaveEntry::factory()->of($period)->taken(1, Tranche::Extra)->create();

    resolve(ExpireExtraDays::class)->handle();

    expect(expiries())->toBe([[$period->id, Tranche::Extra, -2]]);
});

it('never takes off statutory days, not even of a period that can prescribe', function (): void {
    // Anniversary in 2024: past its 18 months since September 2025, with its 12 statutory days untouched.
    $old = periodWithExtra($this->ana, 0, ['anniversary_on' => '2024-03-15', 'extra_expires_on' => '2025-03-15']);
    $mixed = periodWithExtra($this->ana, 2, ['years_of_service' => 2, 'anniversary_on' => '2025-03-15']);

    expect(resolve(ExpireExtraDays::class)->handle())->toBe(1)
        ->and(expiries())->toBe([[$mixed->id, Tranche::Extra, -2]]);

    [$first, $second] = Balances::of([$this->ana->id])[$this->ana->id];
    expect([$first->entitlementId, $first->statutoryLeft, $second->statutoryLeft, $second->extraLeft])->toBe([$old->id, 12, 12, 0]);
});

it('leaves alone the extra days of a period with no expiry', function (): void {
    periodWithExtra($this->ana, 3, ['extra_expires_on' => null]);

    expect(resolve(ExpireExtraDays::class)->handle())->toBe(0)
        ->and(expiries())->toBe([]);
});

it('expires each period of each person on its own', function (): void {
    $beto = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
    $first = periodWithExtra($this->ana, 1, ['anniversary_on' => '2025-03-15']);
    $second = periodWithExtra($this->ana, 2, ['years_of_service' => 2]);
    $notYet = periodWithExtra($this->ana, 4, ['years_of_service' => 3, 'extra_expires_on' => '2026-07-01']);
    $ofBeto = periodWithExtra($beto, 3);

    expect(resolve(ExpireExtraDays::class)->handle())->toBe(3)
        ->and(collect(expiries())->sortBy(fn (array $expiry): int => $expiry[2])->values()->all())->toBe([[$ofBeto->id, Tranche::Extra, -3], [$second->id, Tranche::Extra, -2], [$first->id, Tranche::Extra, -1]])
        ->and(Balances::of([$this->ana->id])[$this->ana->id][2]->entitlementId)->toBe($notYet->id)
        ->and(Balances::of([$this->ana->id])[$this->ana->id][2]->extraLeft)->toBe(4);
});

it('does not touch the periods of another company', function (): void {
    $other = Tenant::factory()->create();
    periodWithExtra(Employee::factory()->create(['tenant_id' => $other->id]), 3);

    expect(resolve(ExpireExtraDays::class)->handle())->toBe(0)
        ->and(expiries())->toBe([]);
});

it('logs each expiry with ids and days', function (): void {
    $period = periodWithExtra($this->ana, 3);
    Log::spy();

    resolve(ExpireExtraDays::class)->handle();

    Log::shouldHaveReceived('info')->with('leave.extra_days_expired', ['entitlement_id' => $period->id, 'employee_id' => $this->ana->id, 'days' => 3])->once();
});

it('runs with the daily grant, in each company', function (): void {
    $period = periodWithExtra($this->ana, 3);
    $other = Tenant::factory()->create();
    LeaveSetting::factory()->create(['tenant_id' => $other->id]);
    $ofOther = periodWithExtra(Employee::factory()->create(['tenant_id' => $other->id]), 2);

    resolve(GrantEntitlements::class)->handle();

    expect(collect(expiries())->sortBy(fn (array $expiry): int => $expiry[2])->values()->all())->toBe([[$period->id, Tranche::Extra, -3], [$ofOther->id, Tranche::Extra, -2]]);
});

it('expires what goes over the carry-over cap when the next period is granted', function (): void {
    $previous = periodWithExtra($this->ana, 5, ['extra_expires_on' => null]);
    Log::spy();

    $granted = resolve(GrantEntitlement::class)->handle($this->ana->id, 2, CarbonImmutable::parse('2027-03-15'), cappedTerms(2));

    expect(expiries())->toBe([[$previous->id, Tranche::Extra, -3]])
        ->and($granted?->extra_days)->toBe(5);
    [$old, $new] = Balances::of([$this->ana->id])[$this->ana->id];
    expect([$old->statutoryLeft, $old->extraLeft, $new->extraLeft])->toBe([12, 2, 5]);
    Log::shouldHaveReceived('info')->with('leave.extra_days_expired', ['entitlement_id' => $previous->id, 'employee_id' => $this->ana->id, 'days' => 3])->once();
});

it('takes what goes over the cap from the oldest period first', function (): void {
    $oldest = periodWithExtra($this->ana, 2, ['anniversary_on' => '2024-03-15', 'extra_expires_on' => null]);
    $middle = periodWithExtra($this->ana, 4, ['years_of_service' => 2, 'anniversary_on' => '2025-03-15', 'extra_expires_on' => null]);
    periodWithExtra($this->ana, 1, ['years_of_service' => 3, 'anniversary_on' => '2026-03-15', 'extra_expires_on' => null]);

    resolve(GrantEntitlement::class)->handle($this->ana->id, 4, CarbonImmutable::parse('2027-03-15'), cappedTerms(4));

    // 7 unused over a cap of 4: 3 go, 2 from the oldest and 1 from the next.
    expect(expiries())->toBe([[$oldest->id, Tranche::Extra, -2], [$middle->id, Tranche::Extra, -1]]);
});

it('counts against the cap only the extra days still unused', function (): void {
    $previous = periodWithExtra($this->ana, 5, ['extra_expires_on' => null]);
    LeaveEntry::factory()->of($previous)->taken(3, Tranche::Extra)->create();

    resolve(GrantEntitlement::class)->handle($this->ana->id, 2, CarbonImmutable::parse('2027-03-15'), cappedTerms(2));

    expect(expiries())->toBe([]);
});

it('carries nothing over with a cap of zero, and everything with no cap', function (?int $cap, array $expired): void {
    $previous = periodWithExtra($this->ana, 5, ['extra_expires_on' => null]);

    resolve(GrantEntitlement::class)->handle($this->ana->id, 2, CarbonImmutable::parse('2027-03-15'), cappedTerms($cap));

    expect(array_map(fn (array $expiry): int => $expiry[2], expiries()))->toBe($expired)
        ->and(Balances::of([$this->ana->id])[$this->ana->id][0]->statutoryLeft)->toBe(12)
        ->and(Balances::of([$this->ana->id])[$this->ana->id][0]->entitlementId)->toBe($previous->id);
})->with([
    'cap of zero' => [0, [-5]],
    'cap reached exactly' => [5, []],
    'no cap' => [null, []],
]);

it('does not count against the cap the extra days of another person', function (): void {
    periodWithExtra(Employee::factory()->create(['tenant_id' => $this->tenant->id]), 5, ['extra_expires_on' => null]);

    resolve(GrantEntitlement::class)->handle($this->ana->id, 2, CarbonImmutable::parse('2027-03-15'), cappedTerms(2));

    expect(expiries())->toBe([]);
});

it('applies no cap when the year of service already has its period', function (): void {
    periodWithExtra($this->ana, 5, ['extra_expires_on' => null]);

    expect(resolve(GrantEntitlement::class)->handle($this->ana->id, 1, CarbonImmutable::parse('2026-03-15'), cappedTerms(0)))->toBeNull()
        ->and(expiries())->toBe([]);
});
