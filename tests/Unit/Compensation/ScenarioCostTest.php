<?php

declare(strict_types=1);

use App\Modules\Compensation\Data\BenefitSpec;
use App\Modules\Compensation\Data\ConceptCost;
use App\Modules\Compensation\Data\CostBreakdown;
use App\Modules\Compensation\Data\CostCut;
use App\Modules\Compensation\Data\IndividualSalary;
use App\Modules\Compensation\Data\MonthlyCost;
use App\Modules\Compensation\Enums\BenefitKind;
use App\Modules\Compensation\Enums\CostConcept;
use App\Modules\Compensation\Enums\Frequency;
use App\Modules\Compensation\Enums\SbcIntegration;
use App\Modules\Compensation\Support\Calculation\Rate;
use App\Modules\Compensation\Support\Calculation\ScenarioCost;
use App\Modules\Organization\Data\HeadcountCell;
use App\Modules\Organization\Enums\StaffGroup;

mutates(ScenarioCost::class);

it('costs a 5 percent salary increase with every drag-along concept', function (): void {
    $cost = ScenarioCost::firstYear(scenarioInput(['raises' => ['unionized' => Rate::scaled(5, 2)]]));

    // By hand from criterios.md, for one person over the 365 days of a year. Before: $500 a day, SBC 524.65 (52,465 cents);
    // after: $525 a day, SBC 525 × 383/365 = 550.89 (55,089 cents). The vacation stays 12 days and the aguinaldo 15.
    //  salary        50,000 × 365 = 18,250,000        → 52,500 × 365 = 19,162,500          Δ  912,500
    //  aguinaldo     15 days = 750,000                → 787,500                            Δ   37,500
    //  premium       12 days × 25 % = 150,000         → 157,500                            Δ    7,500
    // Contributions, each line of the year settled to the cent (sbc × 365 = 19,149,725 → 20,107,485):
    //  fixed (UMA)   20.40 % × 11,731 × 365 = 873,490 both  Δ 0
    //  surplus       1.10 % × (sbc − 35,193) × 365    69,347 → 79,882                      Δ 10,535
    //  pensioners    1.05 %                           201,072 → 211,129                    Δ 10,057
    //  cash          0.70 %                           134,048 → 140,752                    Δ  6,704
    //  disability    1.75 %                           335,120 → 351,881                    Δ 16,761
    //  work risk     0.50 %                            95,749 → 100,537                    Δ  4,788
    //  childcare     1.00 %                           191,497 → 201,075                    Δ  9,578
    //  retirement    2.00 %                           382,995 → 402,150                    Δ 19,155
    //  severance     7.513 %                        1,438,719 → 1,510,675                  Δ 71,956
    //  infonavit     5.00 %                           957,486 → 1,005,374                  Δ 47,888
    //  payroll tax   3 % × (salary + aguinaldo + premium)  574,500 → 603,225              Δ 28,725
    expect(deltasOf($cost))->toBe([
        'salary:salary' => 912_500,
        'benefit:christmas_bonus' => 37_500,
        'benefit:vacation_premium' => 7_500,
        'contribution:imss_fixed' => 0,
        'contribution:imss_surplus' => 10_535,
        'contribution:imss_pensioners' => 10_057,
        'contribution:imss_cash' => 6_704,
        'contribution:imss_disability_life' => 16_761,
        'contribution:work_risk' => 4_788,
        'contribution:imss_childcare' => 9_578,
        'contribution:retirement' => 19_155,
        'contribution:severance_old_age' => 71_956,
        'contribution:infonavit' => 47_888,
        'contribution:payroll_tax' => 28_725,
    ])->and($cost->beforeCents)->toBe(24_404_023)
        ->and($cost->afterCents)->toBe(25_587_670)
        ->and($cost->deltaCents())->toBe(1_183_647)
        ->and($cost->recurringCents)->toBe(1_183_647)
        ->and($cost->people)->toBe(1);
});

it('costs the months of retroactivity at the first month delta', function (): void {
    $cost = ScenarioCost::firstYear(scenarioInput(['raises' => ['unionized' => Rate::scaled(5, 2)], 'retroMonths' => 3]));

    // A twelfth of 1,183,647 is 98,637.25: the first month, with each concept settled to the cent, is within 14 cents of it.
    expect($cost->months[0]->deltaCents())->toBeGreaterThan(98_637 - 14)->toBeLessThan(98_638 + 14)
        ->and($cost->retroactiveCents)->toBe(3 * $cost->months[0]->deltaCents())
        ->and($cost->firstYearCents())->toBe(1_183_647 + $cost->retroactiveCents);
});

it('costs zero for an unchanged scenario and for zero retroactivity', function (): void {
    $unchanged = ScenarioCost::firstYear(scenarioInput(['retroMonths' => 6]));

    expect($unchanged->deltaCents())->toBe(0)
        ->and($unchanged->recurringCents)->toBe(0)
        ->and($unchanged->retroactiveCents)->toBe(0)
        ->and($unchanged->firstYearCents())->toBe(0)
        ->and($unchanged->beforeCents)->toBe(24_404_023)
        ->and($unchanged->afterCents)->toBe(24_404_023)
        ->and(array_filter(deltasOf($unchanged)))->toBe([]);

    $noRetro = ScenarioCost::firstYear(scenarioInput(['raises' => ['unionized' => Rate::scaled(5, 2)], 'retroMonths' => 0]));

    expect($noRetro->retroactiveCents)->toBe(0)
        ->and($noRetro->firstYearCents())->toBe($noRetro->deltaCents());
});

it('costs a new benefit differently from a salary point', function (): void {
    // A bonus of $1,000 a month that does not integrate nor pay payroll tax costs exactly 12 × 100,000 and drags nothing.
    $plain = ScenarioCost::firstYear(scenarioInput(['proposed' => [scenarioBenefit()]]));

    expect($plain->deltaCents())->toBe(1_200_000)
        ->and(deltasOf($plain)['benefit:meal_bonus'])->toBe(1_200_000)
        ->and(array_sum(array_filter(deltasOf($plain), fn (string $key): bool => str_starts_with($key, 'contribution:'), ARRAY_FILTER_USE_KEY)))->toBe(0);

    // The same bonus integrating to the SBC and in the payroll tax: +3,287.67 a day makes the SBC 55,753 and the tax base
    // 1,200,000 more. Infonavit: 55,753 × 365 × 5 % = 1,017,492 against 957,486; payroll tax: 3 % × 1,200,000.
    $integrating = ScenarioCost::firstYear(scenarioInput(['proposed' => [scenarioBenefit(['sbcIntegration' => SbcIntegration::Always, 'inPayrollTax' => true])]]));

    expect(deltasOf($integrating)['benefit:meal_bonus'])->toBe(1_200_000)
        ->and(deltasOf($integrating)['contribution:infonavit'])->toBe(60_006)
        ->and(deltasOf($integrating)['contribution:payroll_tax'])->toBe(36_000);

    // A point of salary ($50,500 a day) drags the aguinaldo (15 days) and the premium (12 days × 25 %) and the contributions.
    $point = ScenarioCost::firstYear(scenarioInput(['raises' => ['unionized' => Rate::scaled(1, 2)]]));

    expect(deltasOf($point)['salary:salary'])->toBe(182_500)
        ->and(deltasOf($point)['benefit:christmas_bonus'])->toBe(7_500)
        ->and(deltasOf($point)['benefit:vacation_premium'])->toBe(1_500)
        ->and($point->deltaCents())->toBeGreaterThan(182_500 + 7_500 + 1_500);
});

it('replaces a benefit by its key and adds the ones that are new', function (): void {
    $current = [scenarioBenefit(['amount' => Rate::scaled(100_000, 0)]), scenarioBenefit(['key' => 'kept', 'amount' => Rate::scaled(10_000, 0)])];
    $proposed = [
        scenarioBenefit(['amount' => Rate::scaled(150_000, 0)]),
        scenarioBenefit(['key' => 'new', 'amount' => Rate::scaled(20_000, 0)]),
        scenarioBenefit(['key' => 'christmas_bonus', 'kind' => BenefitKind::SalaryDays, 'amount' => Rate::scaled(20, 0), 'frequency' => Frequency::Yearly]),
        scenarioBenefit(['key' => 'vacation_premium', 'kind' => BenefitKind::SalaryPercent, 'amount' => Rate::scaled(40, 2), 'frequency' => Frequency::Yearly]),
    ];
    $deltas = deltasOf(ScenarioCost::firstYear(scenarioInput(['benefits' => $current, 'proposed' => $proposed])));

    // 12 × (150,000 − 100,000); 12 × 20,000 new; 5 more days × 50,000; 12 days × (40 % − 25 %) × 50,000.
    expect($deltas['benefit:meal_bonus'])->toBe(600_000)
        ->and($deltas['benefit:kept'])->toBe(0)
        ->and($deltas['benefit:new'])->toBe(240_000)
        ->and($deltas['benefit:christmas_bonus'])->toBe(250_000)
        ->and($deltas['benefit:vacation_premium'])->toBe(90_000);
});

it('caps vouchers at 40 percent of UMA and one peso above', function (): void {
    $vouchers = fn (int $dailyCents): CostBreakdown => ScenarioCost::firstYear(scenarioInput(['proposed' => [scenarioBenefit([
        'key' => 'food_vouchers', 'amount' => Rate::scaled($dailyCents, 0), 'frequency' => Frequency::Daily, 'sbcIntegration' => SbcIntegration::UpToCap,
    ])]]));

    // $46.92 a day is the exemption: it costs 4,692 × 365 and the SBC does not move, so Infonavit does not either.
    // $47.92 a day integrates the peso over it: SBC 52,565, so Infonavit is 52,565 × 365 × 5 % = 959,311 against 957,486.
    expect(deltasOf($vouchers(4_692))['benefit:food_vouchers'])->toBe(1_712_580)
        ->and(deltasOf($vouchers(4_692))['contribution:infonavit'])->toBe(0)
        ->and(deltasOf($vouchers(4_792))['benefit:food_vouchers'])->toBe(1_749_080)
        ->and(deltasOf($vouchers(4_792))['contribution:infonavit'])->toBe(1_825);
});

it('applies the percent to an override above and below the tabulator', function (): void {
    $raises = ['unionized' => Rate::scaled(5, 2)];
    $people = ScenarioCost::firstYear(scenarioInput(['cells' => [], 'individuals' => [scenarioPerson('E-1', 80_000), scenarioPerson('E-2', 30_000)], 'raises' => $raises]));

    // The tabulator says $500 for g1; E-1 earns $800 and E-2 $300. 5 %: $840 and $315, so +4,000 and +1,500 a day for 365 days.
    expect(deltasOf($people)['salary:salary'])->toBe(1_460_000 + 547_500)
        ->and($people->people)->toBe(2);

    $mixed = ScenarioCost::firstYear(scenarioInput([
        'cells' => [scenarioCell(2)], 'individuals' => [scenarioPerson('E-1', 80_000), scenarioPerson('E-2', 30_000)], 'raises' => $raises,
    ]));

    expect(deltasOf($mixed)['salary:salary'])->toBe(2 * 912_500 + 1_460_000 + 547_500)
        ->and($mixed->people)->toBe(4);
});

it('settles the raised daily salary to the cent', function (): void {
    // 333.33 × 1.05 = 349.9965 → 350.00; the year is 35,000 × 365 − 33,333 × 365.
    $cost = ScenarioCost::firstYear(scenarioInput(['cells' => [], 'individuals' => [scenarioPerson('E-1', 33_333)], 'raises' => ['unionized' => Rate::scaled(5, 2)]]));

    expect(deltasOf($cost)['salary:salary'])->toBe((35_000 - 33_333) * 365);
});

it('copies the unionized raise to the trusted unless the trusted have their own', function (): void {
    $people = [scenarioPerson('E-1', 50_000), scenarioPerson('E-2', 50_000, group: StaffGroup::Trusted)];
    $copied = ScenarioCost::firstYear(scenarioInput(['cells' => [], 'individuals' => $people, 'raises' => ['unionized' => Rate::scaled(5, 2)]]));
    $own = ScenarioCost::firstYear(scenarioInput(['cells' => [], 'individuals' => $people, 'raises' => ['unionized' => Rate::scaled(5, 2), 'trusted' => Rate::scaled(10, 2)]]));
    $none = ScenarioCost::firstYear(scenarioInput(['cells' => [], 'individuals' => $people, 'raises' => ['unionized' => Rate::scaled(5, 2), 'trusted' => Rate::zero()]]));

    expect(cutOf($copied->byGroup, 'trusted')?->deltaCents())->toBe(cutOf($copied->byGroup, 'unionized')?->deltaCents())
        ->and(cutOf($own->byGroup, 'trusted')?->deltaCents())->toBeGreaterThan(cutOf($own->byGroup, 'unionized')?->deltaCents() ?? 0)
        ->and(cutOf($own->byGroup, 'unionized')?->deltaCents())->toBe(cutOf($copied->byGroup, 'unionized')?->deltaCents())
        ->and(cutOf($none->byGroup, 'trusted')?->deltaCents())->toBe(0);
});

it('excludes people without grade salary and reports them', function (): void {
    $cells = [scenarioCell(2), scenarioCell(3, grade: 'g-missing'), scenarioCell(1, grade: 'g-missing', hireMonth: '2020-05-01')];
    $input = scenarioInput(['cells' => $cells, 'raises' => ['unionized' => Rate::scaled(5, 2)]]);
    $cost = ScenarioCost::firstYear($input);

    expect($cost->people)->toBe(2)
        ->and($cost->uncostedGrades)->toBe(['g-missing' => 4])
        ->and($cost->gaps)->toBe($input->gaps)
        ->and(deltasOf($cost)['salary:salary'])->toBe(2 * 912_500);
});

it('handles an empty roster and a one-person group', function (): void {
    $empty = ScenarioCost::firstYear(scenarioInput(['cells' => [], 'retroMonths' => 4, 'raises' => ['unionized' => Rate::scaled(5, 2)]]));

    expect($empty->people)->toBe(0)
        ->and($empty->beforeCents)->toBe(0)
        ->and($empty->afterCents)->toBe(0)
        ->and($empty->firstYearCents())->toBe(0)
        ->and($empty->concepts)->toBe([])
        ->and($empty->byGroup)->toBe([])
        ->and($empty->months)->toHaveCount(12);

    $single = ScenarioCost::firstYear(scenarioInput(['raises' => ['unionized' => Rate::scaled(5, 2)]]));
    $group = cutOf($single->byGroup, 'unionized');

    expect($single->byGroup)->toHaveCount(1)
        ->and($group?->people)->toBe(1)
        ->and($group?->deltaCents())->toBe(1_183_647);
});

it('steps vacation days on the anniversary inside the 12 months', function (): void {
    $premium = function (string $effectiveOn, ?HeadcountCell $cell, ?IndividualSalary $person): ?int {
        $cost = ScenarioCost::firstYear(scenarioInput([
            'effectiveOn' => $effectiveOn, 'cells' => $cell instanceof HeadcountCell ? [$cell] : [], 'individuals' => $person instanceof IndividualSalary ? [$person] : [],
        ]));

        return conceptOf($cost, CostConcept::Benefit, 'vacation_premium')?->beforeCents;
    };

    // The premium of a year is 20 days × 25 % × $500 = 250,000 with 5 years of service and 22 days = 275,000 with 6.
    // A cell stands for the 1st of its month: hired in March 2020, the sixth year starts on the 1st of March 2026, month 3 of 12:
    //  (2 × 250,000 + 10 × 275,000) / 12 = 270,833.33
    expect($premium('2026-01-01', scenarioCell(hireMonth: '2020-03-01'), null))->toBe(270_833)
        // Someone hired on March 15, 2020 gets the sixth year on April 1, 2026: (3 × 250,000 + 9 × 275,000) / 12 = 268,750
        ->and($premium('2026-01-01', null, scenarioPerson('E-1', 50_000, '2020-03-15')))->toBe(268_750)
        // Hired on Feb 29, 2020, whose anniversary is Feb 28 in a year without it: on Feb 27 they have 5 years, on Mar 27 they
        // have 6. (250,000 + 11 × 275,000) / 12 = 272,916.67
        ->and($premium('2026-02-27', null, scenarioPerson('E-1', 50_000, '2020-02-29')))->toBe(272_917)
        // and on Feb 28 the sixth year is already running: 12 × 275,000 / 12.
        ->and($premium('2026-02-28', null, scenarioPerson('E-1', 50_000, '2020-02-29')))->toBe(275_000);
});

it('starts a benefit with a minimum tenure in the month the person reaches it', function (): void {
    $bonus = scenarioBenefit(['key' => 'seniority_bonus']);
    $withMinimum = new BenefitSpec('seniority_bonus', $bonus->kind, $bonus->amount, $bonus->frequency, null, [], 6, [], SbcIntegration::Never, null, false, false, false, null);
    $cost = ScenarioCost::firstYear(scenarioInput([
        'cells' => [], 'individuals' => [scenarioPerson('E-1', 50_000, '2025-10-01')], 'proposed' => [$withMinimum],
    ]));

    // Six months of service on April 1, month 4 of 12: it pays 9 months of the $1,000 monthly bonus, 9 × 100,000.
    expect(deltasOf($cost)['benefit:seniority_bonus'])->toBe(900_000);
});

it('has no vacation and no premium on the day of hire, and no division by zero', function (): void {
    $cost = ScenarioCost::firstYear(scenarioInput(['cells' => [scenarioCell(hireMonth: '2026-01-01')], 'raises' => ['unionized' => Rate::scaled(5, 2)]]));

    // 0 years of service in the 12 months: no vacation days, so no premium; the aguinaldo (15 days) still counts.
    expect(conceptOf($cost, CostConcept::Benefit, 'vacation_premium')?->afterCents)->toBe(0)
        ->and(conceptOf($cost, CostConcept::Benefit, 'christmas_bonus')?->beforeCents)->toBe(750_000)
        ->and($cost->deltaCents())->toBeGreaterThan(0);
});

it('adds the cells to the total with exact sums', function (): void {
    $input = scenarioInput([
        'cells' => [
            scenarioCell(7, '2020-03-01'),
            scenarioCell(13, '2018-07-01', area: 'a2', group: StaffGroup::Trusted, state: 'CMX'),
            scenarioCell(3, '2024-11-01', grade: 'g2'),
            scenarioCell(5, '2015-01-01', grade: 'g2', area: null, group: StaffGroup::Trusted),
        ],
        'individuals' => [scenarioPerson('E-1', 61_777, '2012-09-17')],
        'gradeSalaries' => ['g1' => 51_237, 'g2' => 38_491],
        'raises' => ['unionized' => Rate::scaled(435, 4)],
        'proposed' => [scenarioBenefit(['key' => 'bonus', 'kind' => BenefitKind::SalaryPercent, 'amount' => Rate::scaled(3, 2), 'sbcIntegration' => SbcIntegration::Always, 'inPayrollTax' => true])],
        'retroMonths' => 2,
    ]);
    $cost = ScenarioCost::firstYear($input);
    $months = fn (callable $side): int => array_sum(array_map($side, $cost->months));
    $concepts = fn (callable $side): int => array_sum(array_map($side, $cost->concepts));

    expect($cost->people)->toBe(29)
        ->and($cost->months)->toHaveCount(12)
        ->and($months(fn (MonthlyCost $month): int => $month->beforeCents))->toBe($cost->beforeCents)
        ->and($months(fn (MonthlyCost $month): int => $month->afterCents))->toBe($cost->afterCents)
        ->and($concepts(fn (ConceptCost $concept): int => $concept->beforeCents))->toBe($cost->beforeCents)
        ->and($concepts(fn (ConceptCost $concept): int => $concept->afterCents))->toBe($cost->afterCents)
        ->and($concepts(fn (ConceptCost $concept): int => $concept->recurringCents))->toBe($cost->recurringCents)
        ->and($concepts(fn (ConceptCost $concept): int => $concept->retroactiveCents))->toBe($cost->retroactiveCents)
        ->and($cost->firstYearCents())->toBe($cost->deltaCents() + $cost->retroactiveCents);

    foreach ([$cost->byArea, $cost->byGrade, $cost->byGroup] as $cuts) {
        expect(array_sum(array_map(fn (CostCut $cut): int => $cut->beforeCents, $cuts)))->toBe($cost->beforeCents)
            ->and(array_sum(array_map(fn (CostCut $cut): int => $cut->afterCents, $cuts)))->toBe($cost->afterCents)
            ->and(array_sum(array_map(fn (CostCut $cut): int => $cut->people, $cuts)))->toBe(29);
    }

    expect(cutOf($cost->byArea, 'a2')?->people)->toBe(13)
        ->and(cutOf($cost->byArea, null)?->people)->toBe(5)
        ->and(cutOf($cost->byGrade, 'g2')?->people)->toBe(8)
        ->and(cutOf($cost->byGroup, 'trusted')?->people)->toBe(18);
});

it('marks the lines it could not calculate', function (): void {
    expect(scenarioUncalculated(['premium' => null, 'state' => null]))->toBe(['work_risk', 'payroll_tax'])
        ->and(scenarioUncalculated(['premium' => null]))->toBe(['work_risk'])
        ->and(scenarioUncalculated(['state' => null]))->toBe(['payroll_tax'])
        ->and(scenarioUncalculated([]))->toBe([])
        // The state of the cell wins over the default one, and a state without a flat rate is not calculated.
        ->and(scenarioUncalculated(['state' => null, 'cells' => [scenarioCell(state: 'CMX')]]))->toBe([])
        ->and(scenarioUncalculated(['cells' => [scenarioCell(state: 'SIN')]]))->toBe(['payroll_tax'])
        ->and(scenarioUncalculated(['state' => null, 'cells' => [scenarioCell(state: 'CMX'), scenarioCell(hireMonth: '2024-01-01')]]))->toBe(['payroll_tax']);
});

it('takes the payroll tax of the state of the person', function (): void {
    $tax = fn (?string $state): int => deltasOf(ScenarioCost::firstYear(scenarioInput(['state' => null, 'cells' => [scenarioCell(state: $state)], 'raises' => ['unionized' => Rate::scaled(5, 2)]])))['contribution:payroll_tax'];

    // The base grows by 20,107,500 − 19,150,000 = 957,500: 3 % in Jalisco and 4 % in Ciudad de México.
    expect($tax('JAL'))->toBe(28_725)
        ->and($tax('CMX'))->toBe(38_300);
});

it('stops the contributions at 25 UMA and keeps costing what does not depend on the SBC', function (): void {
    // $3,000 a day is over the cap in both cases (SBC 293,275 cents), so the SBC does not move with the raise.
    $cost = ScenarioCost::firstYear(scenarioInput(['cells' => [], 'individuals' => [scenarioPerson('E-1', 300_000)], 'raises' => ['unionized' => Rate::scaled(5, 2)]]));
    $deltas = deltasOf($cost);

    expect($deltas['contribution:infonavit'])->toBe(0)
        ->and($deltas['contribution:imss_childcare'])->toBe(0)
        ->and($deltas['contribution:imss_fixed'])->toBe(0)
        ->and($deltas['salary:salary'])->toBe(15_000 * 365)
        ->and($deltas['contribution:payroll_tax'])->toBeGreaterThan(0);
});

it('carries the stale fiscal keys of the snapshot', function (): void {
    expect(ScenarioCost::firstYear(scenarioInput(['fiscal' => scenarioFiscal(['uma'])]))->staleKeys)->toBe(['uma'])
        ->and(ScenarioCost::firstYear(scenarioInput())->staleKeys)->toBe([]);
});

it('dates the months from the effective date', function (): void {
    $months = ScenarioCost::firstYear(scenarioInput(['effectiveOn' => '2026-01-31']))->months;

    expect($months[0]->month->toDateString())->toBe('2026-01-31')
        ->and($months[1]->month->toDateString())->toBe('2026-02-28')
        ->and($months[2]->month->toDateString())->toBe('2026-03-31')
        ->and($months[11]->month->toDateString())->toBe('2026-12-31');
});

it('keeps an attendance bonus inside 10 percent of the SBC out of the contributions and integrates only the excess over it', function (): void {
    $bonus = fn (int $hundredths): array => ['benefits' => [scenarioBenefit([
        'key' => 'attendance_bonus', 'kind' => BenefitKind::SalaryPercent, 'amount' => Rate::scaled($hundredths, 2),
        'frequency' => Frequency::Monthly, 'sbcIntegration' => SbcIntegration::UpToCap,
    ])]];
    $cash = fn (array $with): int => conceptOf(ScenarioCost::firstYear(scenarioInput($with)), CostConcept::Contribution, 'imss_cash')?->beforeCents ?? -1;

    // The SBC is 52,465 without the bonus; the cash line is 0.70 % of it for 365 days: 134,048.
    // A bonus of 8 % of the salary is 4,000 a day, under the 5,246.5 that 10 % allows: the SBC does not move.
    // One of 15 % is 7,500 a day: the 2,253.5 over the 10 % integrate on the 52,465.75 of the salary: 54,719.25 → 54,719, and 139,807 in the line.
    expect($cash([]))->toBe(134_048)
        ->and($cash($bonus(8)))->toBe(134_048)
        ->and($cash($bonus(15)))->toBe(139_807);
});
