<?php

declare(strict_types=1);

use App\Modules\Compensation\Support\Calculation\Rate;
use App\Modules\Compensation\Support\Calculation\SeniorityPremium;
use App\Modules\Organization\Enums\StaffGroup;

mutates(SeniorityPremium::class);

it('estimates the seniority premium from 12,000,000 to 12,600,000 cents with +5 percent and 20 years', function (): void {
    // criterios.md 5e): $500 a day, 20 years: 12 × 20 × 500 = $120,000; with +5 % ($525 a day) $126,000.
    $estimate = SeniorityPremium::estimate(scenarioInput([
        'cells' => [scenarioCell(hireMonth: '2006-01-01')],
        'raises' => ['unionized' => Rate::scaled(5, 2)],
    ]));

    expect($estimate->beforeCents)->toBe(12_000_000)
        ->and($estimate->afterCents)->toBe(12_600_000)
        ->and($estimate->deltaCents())->toBe(600_000)
        ->and($estimate->people)->toBe(1);
});

it('caps the premium base at two minimum wages', function (): void {
    // 2 × $315.04 = $630.08 a day: $700 and $735 (after +5 %) both pay 12 × 20 × 630.08.
    $estimate = SeniorityPremium::estimate(scenarioInput([
        'cells' => [scenarioCell(hireMonth: '2006-01-01')],
        'gradeSalaries' => ['g1' => 70_000],
        'raises' => ['unionized' => Rate::scaled(5, 2)],
    ]));

    expect($estimate->beforeCents)->toBe(15_121_920)
        ->and($estimate->afterCents)->toBe(15_121_920);
});

it('counts a base of one cent under the cap in full and stops at the cap, one cent over', function (): void {
    $under = SeniorityPremium::estimate(scenarioInput(['cells' => [scenarioCell(hireMonth: '2006-01-01')], 'gradeSalaries' => ['g1' => 63_007]]));
    $at = SeniorityPremium::estimate(scenarioInput(['cells' => [scenarioCell(hireMonth: '2006-01-01')], 'gradeSalaries' => ['g1' => 63_008]]));

    $over = SeniorityPremium::estimate(scenarioInput(['cells' => [scenarioCell(hireMonth: '2006-01-01')], 'gradeSalaries' => ['g1' => 63_009]]));

    expect($under->beforeCents)->toBe(240 * 63_007)
        ->and($at->beforeCents)->toBe(240 * 63_008)
        ->and($over->beforeCents)->toBe(240 * 63_008);
});

it('pays it only to whoever has 15 years of service on the effective date', function (): void {
    $on = fn (string $hired): int => SeniorityPremium::estimate(scenarioInput([
        'effectiveOn' => '2026-01-01',
        'cells' => [scenarioCell(hireMonth: $hired)],
    ]))->people;

    // A cell stands for the 1st of its month: hired in January 2011 has 15 years on 2026-01-01; in February 2011, 14.
    expect($on('2011-01-01'))->toBe(1)
        ->and($on('2011-02-01'))->toBe(0)
        ->and($on('2005-01-01'))->toBe(1);
});

it('uses the exact hire date of a person with an individual salary', function (): void {
    $on = fn (string $hired): int => SeniorityPremium::estimate(scenarioInput([
        'effectiveOn' => '2026-03-01',
        'cells' => [],
        'individuals' => [scenarioPerson('E-1', 60_000, $hired)],
    ]))->beforeCents;

    // 15 years complete on 2026-03-01 for 2011-03-01 but not for 2011-03-02.
    expect($on('2011-03-01'))->toBe(12 * 15 * 60_000)
        ->and($on('2011-03-02'))->toBe(0);
});

it('multiplies by the people of a cell and adds the cells and the individuals', function (): void {
    $estimate = SeniorityPremium::estimate(scenarioInput([
        'cells' => [scenarioCell(3, '2006-01-01'), scenarioCell(2, '2010-06-01', group: StaffGroup::Trusted)],
        'individuals' => [scenarioPerson('E-1', 60_000, '2001-01-01')],
    ]));

    // 3 × 12 × 20 × 500 + 2 × 12 × 15 × 500 (15 years, 7 months → 15) + 12 × 25 × 600.
    expect($estimate->beforeCents)->toBe(3 * 12 * 20 * 50_000 + 2 * 12 * 15 * 50_000 + 12 * 25 * 60_000)
        ->and($estimate->people)->toBe(6);
});

it('adds to the base the benefits that count for the premium, from the contract and the proposal', function (): void {
    // $1,000 a month = 1,200,000 cents a year = 3,287.67 cents a day on top of the 50,000.
    $counts = scenarioBenefit(['key' => 'attendance', 'inSeniorityBase' => true]);
    $doesNot = scenarioBenefit(['key' => 'other']);
    $proposed = scenarioBenefit(['key' => 'new_one', 'inSeniorityBase' => true]);
    $cells = [scenarioCell(hireMonth: '2006-01-01')];

    $with = SeniorityPremium::estimate(scenarioInput(['cells' => $cells, 'benefits' => [$counts, $doesNot]]));
    $withNew = SeniorityPremium::estimate(scenarioInput(['cells' => $cells, 'benefits' => [$doesNot], 'proposed' => [$proposed]]));

    // 240 × (50,000 + 1,200,000 ÷ 365) = 12,789,041.09 → 12,789,041.
    expect($with->beforeCents)->toBe(12_789_041)->and($with->afterCents)->toBe(12_789_041)
        ->and($withNew->beforeCents)->toBe(12_000_000)->and($withNew->afterCents)->toBe(12_789_041);
});

it('leaves out people without a tabulator salary and has no premium for an empty roster', function (): void {
    $withoutSalary = SeniorityPremium::estimate(scenarioInput(['cells' => [scenarioCell(hireMonth: '2006-01-01', grade: 'unknown')]]));
    $empty = SeniorityPremium::estimate(scenarioInput(['cells' => []]));

    expect($withoutSalary->beforeCents)->toBe(0)->and($withoutSalary->people)->toBe(0)
        ->and($empty->afterCents)->toBe(0);
});
