<?php

declare(strict_types=1);

use App\Modules\Compensation\Data\GradeRaise;
use App\Modules\Compensation\Enums\PayGroup;
use App\Modules\Compensation\Support\Calculation\Rate;
use App\Modules\Compensation\Support\Calculation\ScenarioCost;
use App\Modules\Organization\Enums\StaffGroup;

mutates(ScenarioCost::class);

// A monthly amount is turned into a daily one with the month of 30.4 days the report uses (salario diario × 30.4).

it('costs a fixed monthly amount as its daily share of 30.4 days, the same as the percent that gives it', function (): void {
    // $608 a month is $20 a day: on $500 that is 4 %, so both scenarios cost exactly the same.
    $amount = ScenarioCost::firstYear(scenarioInput(['amounts' => ['unionized' => 60_800]]));
    $percent = ScenarioCost::firstYear(scenarioInput(['raises' => ['unionized' => Rate::scaled(4, 2)]]));

    expect(deltasOf($amount)['salary:salary'])->toBe(2_000 * 365)
        ->and($amount->deltaCents())->toBe($percent->deltaCents())
        ->and($amount->beforeCents)->toBe($percent->beforeCents);
});

it('settles the daily salary with the amount to the cent once', function (): void {
    // $100 a month is 328.947… cents a day: the salary of 50,328.947… is settled to 50,329.
    $cost = ScenarioCost::firstYear(scenarioInput(['amounts' => ['unionized' => 10_000]]));

    expect(deltasOf($cost)['salary:salary'])->toBe(329 * 365);
});

it('gives the amount to whoever has an individual salary, whatever they earn', function (): void {
    $cost = ScenarioCost::firstYear(scenarioInput(['cells' => [], 'individuals' => [scenarioPerson('E-1', 80_000), scenarioPerson('E-2', 30_000)], 'amounts' => ['unionized' => 60_800]]));

    expect(deltasOf($cost)['salary:salary'])->toBe(2 * 2_000 * 365);
});

it('copies the unionized amount to the trusted unless the trusted have a change of their own', function (): void {
    $people = [scenarioPerson('E-1', 50_000), scenarioPerson('E-2', 50_000, group: StaffGroup::Trusted)];
    $copied = ScenarioCost::firstYear(scenarioInput(['cells' => [], 'individuals' => $people, 'amounts' => ['unionized' => 60_800]]));
    $own = ScenarioCost::firstYear(scenarioInput(['cells' => [], 'individuals' => $people, 'amounts' => ['unionized' => 60_800, 'trusted' => 30_400]]));
    $percent = ScenarioCost::firstYear(scenarioInput(['cells' => [], 'individuals' => $people, 'amounts' => ['unionized' => 60_800], 'raises' => ['trusted' => Rate::zero()]]));

    expect(cutOf($copied->byGroup, 'trusted')?->deltaCents())->toBe(cutOf($copied->byGroup, 'unionized')?->deltaCents())
        ->and(cutOf($own->byGroup, 'trusted')?->deltaCents())->toBeLessThan(cutOf($own->byGroup, 'unionized')?->deltaCents() ?? 0)->toBeGreaterThan(0)
        ->and(cutOf($percent->byGroup, 'trusted')?->deltaCents())->toBe(0);
});

it('costs nothing for an amount of zero', function (): void {
    expect(ScenarioCost::firstYear(scenarioInput(['amounts' => ['unionized' => 0], 'retroMonths' => 3]))->firstYearCents())->toBe(0);
});

it('says what one month of retroactivity costs, also when the scenario has none', function (): void {
    $none = ScenarioCost::firstYear(scenarioInput(['raises' => ['unionized' => Rate::scaled(5, 2)]]));
    $most = ScenarioCost::firstYear(scenarioInput(['raises' => ['unionized' => Rate::scaled(5, 2)], 'retroMonths' => 36]));

    expect($none->retroactiveMonthCents())->toBe($none->months[0]->deltaCents())->toBeGreaterThan(0)
        ->and($none->retroactiveCents)->toBe(0)
        ->and($most->retroactiveMonthCents())->toBe($none->retroactiveMonthCents())
        ->and($most->retroactiveCents)->toBe(36 * $most->retroactiveMonthCents())
        ->and(ScenarioCost::firstYear(scenarioInput(['retroMonths' => 36]))->retroactiveMonthCents())->toBe(0);
});

it('says what each person of a category receives more a month, by group', function (): void {
    $input = scenarioInput([
        'cells' => [scenarioCell(2), scenarioCell(1, hireMonth: '2020-01-01'), scenarioCell(3, grade: 'g2', group: StaffGroup::Trusted), scenarioCell(1, grade: 'g2'), scenarioCell(4, grade: 'g-missing')],
        'individuals' => [scenarioPerson('E-1', 90_000)],
        'gradeSalaries' => ['g1' => 50_000, 'g2' => 33_333],
        'raises' => ['unionized' => Rate::scaled(5, 2), 'trusted' => Rate::scaled(3, 2)],
    ]);

    // g1 unionized: 50,000 → 52,500, 2,500 × 30.4 = 76,000. g2 trusted: 33,333 → 34,333 (34,332.99), 1,000 × 30.4 = 30,400;
    // g2 unionized: 33,333 → 35,000 (34,999.65), 1,667 × 30.4 = 50,676.8 → 50,677. Nobody without a salary, no individual.
    expect(ScenarioCost::monthlyRaises($input))->toEqual([
        new GradeRaise('g1', PayGroup::Unionized, 3, 50_000, 76_000),
        new GradeRaise('g2', PayGroup::Trusted, 3, 33_333, 30_400),
        new GradeRaise('g2', PayGroup::Unionized, 1, 33_333, 50_677),
    ]);
});

it('gives each category the same amount a month, and nothing to an empty roster', function (): void {
    $input = scenarioInput(['cells' => [scenarioCell(2), scenarioCell(1, grade: 'g2')], 'gradeSalaries' => ['g1' => 50_000, 'g2' => 90_000], 'amounts' => ['unionized' => 60_800]]);

    expect(array_map(fn (GradeRaise $raise): int => $raise->monthlyRaiseCents, ScenarioCost::monthlyRaises($input)))->toBe([60_800, 60_800])
        ->and(ScenarioCost::monthlyRaises(scenarioInput(['cells' => []])))->toBe([])
        ->and(ScenarioCost::monthlyRaises(scenarioInput()))->toEqual([new GradeRaise('g1', PayGroup::Unionized, 1, 50_000, 0)]);
});
