<?php

declare(strict_types=1);

use App\Modules\Compensation\Data\CostBreakdown;
use App\Modules\Compensation\Data\CostCut;
use App\Modules\Compensation\Support\SharedGroups;
use App\Modules\Organization\Data\RosterGaps;
use App\Modules\Results\Contracts\SmallGroups;

mutates(SharedGroups::class);

beforeEach(function (): void {
    config(['results.min_group' => 5]);
});

/**
 * @param  array<string, int>  $people  people by group; the money is 1,000 cents before and 1,100 after for each person
 * @return list<CostCut>
 */
function cutsOf(array $people): array
{
    $cuts = [];

    foreach ($people as $key => $count) {
        $cuts[] = new CostCut($key === '' ? null : $key, $count, $count * 1_000, $count * 1_100);
    }

    return $cuts;
}

/** @param  list<CostCut>  $byArea */
function breakdownOf(array $byArea, ?array $byGrade = null, ?array $byGroup = null): CostBreakdown
{
    return new CostBreakdown(0, 0, 0, 0, 0, [], [], $byArea, $byGrade ?? $byArea, $byGroup ?? $byArea, [], [], [], new RosterGaps(0, 0, [], 0, 0));
}

/** @return array<string, array{int, int, int}> people, before, after by key of the cuts of the breakdown's areas */
function shownAreas(CostBreakdown $cost): array
{
    $shown = [];

    foreach ($cost->byArea as $cut) {
        $shown[$cut->key ?? ''] = [$cut->people, $cut->beforeCents, $cut->afterCents];
    }

    return $shown;
}

function shared(CostBreakdown $cost): CostBreakdown
{
    return SharedGroups::apply($cost, resolve(SmallGroups::class));
}

it('keeps the groups that all reach the minimum as they are', function (): void {
    $shown = shownAreas(shared(breakdownOf(cutsOf(['a' => 20, 'b' => 6, 'c' => 5]))));

    expect($shown)->toBe(['a' => [20, 20_000, 22_000], 'b' => [6, 6_000, 6_600], 'c' => [5, 5_000, 5_500]]);
});

it('folds the groups under the minimum into Others', function (): void {
    $shown = shownAreas(shared(breakdownOf(cutsOf(['a' => 20, 'b' => 12, 'c' => 3, 'd' => 2]))));

    // c and d are 3 and 2 people: together 5, the minimum, so they are shown as one group that tells nothing about either.
    expect($shown)->toBe(['a' => [20, 20_000, 22_000], 'b' => [12, 12_000, 13_200], SharedGroups::OTHERS => [5, 5_000, 5_500]]);
});

it('adds the next smallest group when Others is still under the minimum, so subtracting subtotals from the total reveals nothing', function (): void {
    $shown = shownAreas(shared(breakdownOf(cutsOf(['a' => 20, 'b' => 12, 'c' => 3]))));

    // Others would be 3 people (c alone) and 20 − (20 + 12) would hand it over: the next smallest, b, goes into Others too.
    expect($shown)->toBe(['a' => [20, 20_000, 22_000], SharedGroups::OTHERS => [15, 15_000, 16_500]]);
});

it('keeps the totals exact whatever is folded', function (): void {
    $cuts = cutsOf(['a' => 20, 'b' => 12, 'c' => 3, 'd' => 2, 'e' => 7]);
    $shown = shownAreas(shared(breakdownOf($cuts)));

    expect(array_sum(array_column($shown, 0)))->toBe(44)
        ->and(array_sum(array_column($shown, 1)))->toBe(44_000)
        ->and(array_sum(array_column($shown, 2)))->toBe(48_400);
});

it('hides every cut but the total when only one group would be visible', function (): void {
    // Folding the one small group takes the next smallest with it, and what is left is a single group: the total itself.
    expect(shared(breakdownOf(cutsOf(['a' => 20, 'b' => 3])))->byArea)->toBe([])
        ->and(shared(breakdownOf(cutsOf(['a' => 4, 'b' => 3])))->byArea)->toBe([])
        ->and(shared(breakdownOf(cutsOf(['a' => 40])))->byArea)->toBe([])
        ->and(shared(breakdownOf([]))->byArea)->toBe([]);
});

it('treats a group with no key as a group like any other', function (): void {
    $shown = shownAreas(shared(breakdownOf(cutsOf(['a' => 20, '' => 8]))));

    expect($shown)->toBe(['a' => [20, 20_000, 22_000], '' => [8, 8_000, 8_800]]);
});

it('applies the same rule to each of the three cuts on its own', function (): void {
    $cost = shared(breakdownOf(cutsOf(['a' => 20, 'b' => 3]), cutsOf(['g1' => 20, 'g2' => 15]), cutsOf(['x' => 9, 'y' => 8, 'z' => 1])));

    expect($cost->byArea)->toBe([])
        ->and(array_map(fn (CostCut $cut): ?string => $cut->key, $cost->byGrade))->toBe(['g1', 'g2'])
        ->and(array_map(fn (CostCut $cut): ?string => $cut->key, $cost->byGroup))->toBe(['x', SharedGroups::OTHERS]);
});

it('keeps a group at exactly the minimum and folds one a person under it', function (): void {
    expect(shownAreas(shared(breakdownOf(cutsOf(['a' => 20, 'b' => 5, 'c' => 4])))))->toBe(['a' => [20, 20_000, 22_000], SharedGroups::OTHERS => [9, 9_000, 9_900]]);
});

it('leaves everything but the cuts of the breakdown as it was', function (): void {
    $cost = new CostBreakdown(100, 200, 300, 400, 7, [], [], cutsOf(['a' => 20, 'b' => 3]), [], [], ['g' => 1], ['work_risk'], ['uma'], new RosterGaps(9, 7, ['E-1'], 1, 0));

    $shared = shared($cost);

    expect($shared->beforeCents)->toBe(100)->and($shared->afterCents)->toBe(200)->and($shared->recurringCents)->toBe(300)
        ->and($shared->retroactiveCents)->toBe(400)->and($shared->people)->toBe(7)
        ->and($shared->uncalculatedLines)->toBe(['work_risk'])->and($shared->staleKeys)->toBe(['uma']);
});
