<?php

declare(strict_types=1);

use App\Modules\Leave\Enums\EntryKind;
use App\Modules\Leave\Enums\Tranche;
use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveEntry;
use App\Modules\Leave\Support\Balances;
use App\Modules\Leave\Support\PeriodBalance;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

mutates(Balances::class);

beforeEach(function (): void {
    workIn($this->tenant = Tenant::factory()->create());
    $this->ana = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function periodOf(Employee $person, array $attributes = []): LeaveEntitlement
{
    return LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $person->tenant_id, 'employee_id' => $person->id, ...$attributes]);
}

function leaveAdvance(Employee $person, int $days): LeaveEntry
{
    return LeaveEntry::factory()->create(['tenant_id' => $person->tenant_id, 'employee_id' => $person->id, 'leave_entitlement_id' => null, 'kind' => EntryKind::Taken, 'days' => -$days]);
}

it('sums the ledger of a period by tranche', function (): void {
    $period = periodOf($this->ana, ['statutory_days' => 12, 'extra_days' => 3]);
    LeaveEntry::factory()->of($period)->taken(5)->create();

    expect(Balances::of([$this->ana->id]))->toEqual([$this->ana->id => [
        new PeriodBalance($period->id, CarbonImmutable::parse('2026-03-15'), CarbonImmutable::parse('2026-09-15'), CarbonImmutable::parse('2027-09-15'), 7, 3, 15),
    ]])->and(Balances::available($this->ana->id))->toBe(10);
});

it('counts every kind of entry, on its own tranche', function (): void {
    $period = periodOf($this->ana, ['statutory_days' => 12, 'extra_days' => 3]);
    LeaveEntry::factory()->of($period)->taken(2, Tranche::Extra)->create();
    LeaveEntry::factory()->of($period)->create(['kind' => EntryKind::Expired, 'tranche' => Tranche::Extra, 'days' => -1]);
    LeaveEntry::factory()->of($period)->create(['kind' => EntryKind::Opening, 'tranche' => Tranche::Statutory, 'days' => 4]);

    [$balance] = Balances::of([$this->ana->id])[$this->ana->id];

    expect([$balance->statutoryLeft, $balance->extraLeft])->toBe([16, 0]);
});

it('gives a period with no entries a balance of zero', function (): void {
    LeaveEntitlement::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);

    [$balance] = Balances::of([$this->ana->id])[$this->ana->id];

    expect([$balance->statutoryLeft, $balance->extraLeft])->toBe([0, 0]);
});

it('lists the periods of each person from the oldest to the newest', function (): void {
    $beto = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
    $third = periodOf($this->ana, ['years_of_service' => 3, 'anniversary_on' => '2026-03-15', 'statutory_days' => 16]);
    $first = periodOf($this->ana, ['years_of_service' => 1, 'anniversary_on' => '2024-03-15']);
    $second = periodOf($this->ana, ['years_of_service' => 2, 'anniversary_on' => '2025-03-15', 'statutory_days' => 14]);
    $ofBeto = periodOf($beto);

    $balances = Balances::of([$this->ana->id, $beto->id]);

    expect(array_map(fn (PeriodBalance $balance): string => $balance->entitlementId, $balances[$this->ana->id]))->toBe([$first->id, $second->id, $third->id])
        ->and(array_map(fn (PeriodBalance $balance): string => $balance->entitlementId, $balances[$beto->id]))->toBe([$ofBeto->id])
        ->and(Balances::available($this->ana->id))->toBe(42)
        ->and(Balances::available($beto->id))->toBe(12);
});

it('answers only for the people asked, and nothing for who has no periods', function (): void {
    periodOf($this->ana);
    $beto = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
    periodOf($beto);
    $new = Employee::factory()->create(['tenant_id' => $this->tenant->id]);

    expect(array_keys(Balances::of([$this->ana->id, $new->id])))->toBe([$this->ana->id])
        ->and(Balances::of([]))->toBe([])
        ->and(Balances::available($new->id))->toBe(0);
});

it('keeps apart the days given in advance, as a balance below zero', function (): void {
    $beto = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
    $period = periodOf($this->ana);
    LeaveEntry::factory()->of($period)->taken(12)->create();
    leaveAdvance($this->ana, 2);
    leaveAdvance($this->ana, 1);
    leaveAdvance($beto, 4);

    expect(Balances::advanced([$this->ana->id]))->toBe([$this->ana->id => -3])
        ->and(Balances::advanced([$this->ana->id, $beto->id]))->toBe([$this->ana->id => -3, $beto->id => -4])
        ->and(Balances::advanced([]))->toBe([])
        ->and(Balances::of([$this->ana->id])[$this->ana->id][0]->statutoryLeft)->toBe(0)
        ->and(Balances::available($this->ana->id))->toBe(-3);
});

it('does not count the entries of a period as days in advance', function (): void {
    periodOf($this->ana);

    expect(Balances::advanced([$this->ana->id]))->toBe([]);
});

it('does not read the periods or the ledger of another company', function (): void {
    $period = periodOf($this->ana);
    leaveAdvance($this->ana, 2);

    workIn(Tenant::factory()->create());
    expect(Balances::of([$this->ana->id]))->toBe([])
        ->and(Balances::advanced([$this->ana->id]))->toBe([])
        ->and(Balances::available($this->ana->id))->toBe(0);

    workIn($this->tenant);
    expect(Balances::available($period->employee_id))->toBe(10);
});

it('reads the balances of fifty people with the queries of five', function (): void {
    $count = function (int $people): int {
        $ids = Employee::factory()->count($people)->create(['tenant_id' => $this->tenant->id])->map(function (Employee $person): string {
            periodOf($person);

            return $person->id;
        })->all();
        DB::flushQueryLog();
        DB::enableQueryLog();
        expect(Balances::of($ids))->toHaveCount($people);
        Balances::advanced($ids);
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    expect($count(50))->toBe($count(5))->toBe(2);
});
