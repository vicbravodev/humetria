<?php

declare(strict_types=1);

use App\Modules\Compensation\Data\ContributionLine;
use App\Modules\Compensation\Data\FiscalSnapshot;
use App\Modules\Compensation\Support\Calculation\EmployerContributions;
use App\Modules\Compensation\Support\Calculation\Rate;

mutates(EmployerContributions::class);

/**
 * The 2026 values of docs/normas/compensation/criterios.md, written out by hand (not read from the seeder): UMA $117.31,
 * minimum wage $315.04, the rates of section 2 and the cesantia y vejez column of the given year (section 2.1).
 *
 * @param  list<int>  $severance  percent × 1000 for ranges 1 to 8
 */
function fiscalFor(array $severance): FiscalSnapshot
{
    $values = [
        'uma' => Rate::scaled(11_731, 0),
        'minimum_wage' => Rate::scaled(31_504, 0),
        'imss_fixed' => Rate::scaled(2_040, 4),
        'imss_surplus' => Rate::scaled(110, 4),
        'imss_surplus_umas' => Rate::scaled(3, 0),
        'imss_pensioners' => Rate::scaled(105, 4),
        'imss_cash' => Rate::scaled(70, 4),
        'imss_disability_life' => Rate::scaled(175, 4),
        'imss_childcare' => Rate::scaled(100, 4),
        'retirement' => Rate::scaled(200, 4),
        'infonavit' => Rate::scaled(500, 4),
        'payroll_tax:JAL' => Rate::scaled(300, 4),
        'payroll_tax:CMX' => Rate::scaled(400, 4),
    ];

    foreach ([2 => 150, 3 => 200, 4 => 250, 5 => 300, 6 => 350, 7 => 400] as $bracket => $limit) {
        $values["severance_old_age_limit.{$bracket}"] = Rate::scaled($limit, 2);
    }

    foreach ($severance as $index => $percent) {
        $values['severance_old_age.'.($index + 1)] = Rate::scaled($percent, 5);
    }

    return new FiscalSnapshot($values, []);
}

function fiscal2026(): FiscalSnapshot
{
    return fiscalFor([3150, 3676, 4851, 5556, 6026, 6361, 6613, 7513]);
}

function fiscal2028(): FiscalSnapshot
{
    return fiscalFor([3150, 3939, 5701, 6759, 7464, 7967, 8345, 9694]);
}

/** @return array<string, int> */
function contributions(int $sbc, int $days, ?FiscalSnapshot $fiscal = null, ?Rate $premium = null, ?string $state = null, int $taxable = 0): array
{
    $lines = [];

    foreach (EmployerContributions::forPerson($sbc, $days, $fiscal ?? fiscal2026(), $premium, $state, $taxable) as $line) {
        $lines[$line->key] = $line->cents;
    }

    return $lines;
}

it('gives the eleven lines in a stable order', function (): void {
    $lines = EmployerContributions::forPerson(52_465, 30, fiscal2026(), null, null, 0);

    expect(array_map(fn (ContributionLine $line): string => $line->key, $lines))->toBe([
        'imss_fixed', 'imss_surplus', 'imss_pensioners', 'imss_cash', 'imss_disability_life', 'work_risk',
        'imss_childcare', 'retirement', 'severance_old_age', 'infonavit', 'payroll_tax',
    ]);
});

it('computes each line for a SBC of 524.65 over 30 days', function (): void {
    // By hand from criterios.md, per day in cents, × 30, nearest cent (a half goes up):
    //  fixed       20.40 % × 11,731            = 2,393.124   → 71,793.72  → 71,794
    //  surplus     1.10 % × (52,465 − 35,193)  =   189.992   →  5,699.76  →  5,700
    //  pensioners  1.05 % × 52,465             =   550.8825  → 16,526.475 → 16,526
    //  cash        0.70 % × 52,465             =   367.255   → 11,017.65  → 11,018
    //  disability  1.75 % × 52,465             =   918.1375  → 27,544.125 → 27,544
    //  childcare   1.00 % × 52,465             =   524.65    → 15,739.5   → 15,740 (the half goes up)
    //  retirement  2.00 % × 52,465             = 1,049.3     → 31,479
    //  severance   7.513 % × 52,465            = 3,941.69545 → 118,250.8635 → 118,251
    //  infonavit   5.00 % × 52,465             = 2,623.25    → 78,697.5   → 78,698 (the half goes up)
    expect(contributions(52_465, 30))->toBe([
        'imss_fixed' => 71_794,
        'imss_surplus' => 5_700,
        'imss_pensioners' => 16_526,
        'imss_cash' => 11_018,
        'imss_disability_life' => 27_544,
        'work_risk' => 0,
        'imss_childcare' => 15_740,
        'retirement' => 31_479,
        'severance_old_age' => 118_251,
        'infonavit' => 78_698,
        'payroll_tax' => 0,
    ]);
});

it('reproduces the daily breakdown of criterios.md: 125.58 pesos, 23.94 percent of the SBC', function (): void {
    $daily = contributions(52_465, 1);

    // 23.93 + 1.90 + 5.51 + 3.67 + 9.18 + 5.25 + 10.49 + 39.42 + 26.23, each line to the cent.
    expect($daily)->toMatchArray([
        'imss_fixed' => 2_393, 'imss_surplus' => 190, 'imss_pensioners' => 551, 'imss_cash' => 367,
        'imss_disability_life' => 918, 'imss_childcare' => 525, 'retirement' => 1_049,
        'severance_old_age' => 3_942, 'infonavit' => 2_623,
    ])->and(array_sum($daily))->toBe(12_558)
        // 125.58 ÷ 524.65 = 23.94 %
        // 12,558 ÷ 52,465 = 23.935… %, which criterios.md shows as 23.94 %.
        ->and(Rate::fraction(array_sum($daily), 52_465)->of(10_000))->toBe(2_394);
});

it('bases the fixed fee on the UMA and not on the salary', function (): void {
    expect(contributions(31_504, 30)['imss_fixed'])->toBe(71_794)
        ->and(contributions(293_275, 30)['imss_fixed'])->toBe(71_794);
});

it('pays no surplus up to exactly 3 UMA and does from one cent above', function (): void {
    // 3 UMA = 35,193 cents. Over a year (365 days) one cent of surplus is 1.10 % × 365 = 4.015 → 4 cents.
    expect(contributions(35_192, 365)['imss_surplus'])->toBe(0)
        ->and(contributions(35_193, 365)['imss_surplus'])->toBe(0)
        ->and(contributions(35_194, 365)['imss_surplus'])->toBe(4)
        // 400.00 pesos: 1.10 % × (40,000 − 35,193) = 52.877/day → × 30 = 1,586.31 → 1,586
        ->and(contributions(40_000, 30)['imss_surplus'])->toBe(1_586);
});

it('caps the SBC at 25 UMA for the IMSS and Infonavit', function (): void {
    $atCap = contributions(293_275, 30);

    // Childcare 1 % × 293,275 × 30 = 87,982.5 → 87,983; infonavit 5 % → 439,912.5 → 439,913.
    expect($atCap['imss_childcare'])->toBe(87_983)
        ->and($atCap['infonavit'])->toBe(439_913)
        ->and(contributions(293_276, 30))->toBe($atCap)
        ->and(contributions(300_000, 30))->toBe($atCap)
        ->and(contributions(293_274, 30))->not->toBe($atCap);
});

it('raises a SBC under the minimum wage to the minimum wage', function (): void {
    $atFloor = contributions(31_504, 30);

    // Childcare 1 % × 31,504 × 30 = 9,451.2 → 9,451; the surplus is zero below 3 UMA.
    expect($atFloor['imss_childcare'])->toBe(9_451)
        ->and($atFloor['imss_surplus'])->toBe(0)
        ->and(contributions(20_000, 30))->toBe($atFloor)
        ->and(contributions(0, 30))->toBe($atFloor)
        ->and(contributions(31_505, 30))->not->toBe($atFloor);
});

it('changes the cesantia y vejez rate with the year', function (): void {
    // 7.513 % in 2026 and 9.694 % in 2028 for 4.01 UMA or more; × 52,465 × 30.
    //  2028: 9.694 % × 52,465 = 5,085.9571 → × 30 = 152,578.713 → 152,579
    expect(contributions(52_465, 30, fiscal2026())['severance_old_age'])->toBe(118_251)
        ->and(contributions(52_465, 30, fiscal2028())['severance_old_age'])->toBe(152_579);
});

it('takes the cesantia y vejez rate from the range of the SBC', function (): void {
    // 330.00 pesos is 2.81 UMA (range "2.51 a 3.00 UMA"): 6.026 % in 2026 → 6.026 % × 33,000 = 1,988.58 → × 30 = 59,657.4 → 59,657
    // 31,504 (exactly the minimum wage) is range "1.0 SM": 3.150 % → 992.376 → × 30 = 29,771.28 → 29,771
    // 100.00 pesos of SBC is raised to the minimum wage first, so it is also range 1.
    expect(contributions(33_000, 30)['severance_old_age'])->toBe(59_657)
        ->and(contributions(31_504, 30)['severance_old_age'])->toBe(29_771)
        ->and(contributions(10_000, 30)['severance_old_age'])->toBe(29_771);
});

it('computes the work risk premium on the SBC and the days', function (): void {
    // Class I average premium 0.54355 %: 0.0054355 × 52,465 × 30 = 8,555.205 → 8,555
    $lines = EmployerContributions::forPerson(52_465, 30, fiscal2026(), Rate::scaled(54_355, 7), null, 0);
    $risk = array_values(array_filter($lines, fn (ContributionLine $line): bool => $line->key === 'work_risk'))[0];

    expect($risk->cents)->toBe(8_555)->and($risk->calculated)->toBeTrue();
});

it('computes the payroll tax on the base the caller gives', function (): void {
    // Jalisco 3 % × $10,000.00 = $300.00; Ciudad de México 4 % = $400.00; the SBC does not matter.
    expect(contributions(52_465, 30, state: 'JAL', taxable: 1_000_000)['payroll_tax'])->toBe(30_000)
        ->and(contributions(52_465, 30, state: 'CMX', taxable: 1_000_000)['payroll_tax'])->toBe(40_000)
        ->and(contributions(293_275, 30, state: 'JAL', taxable: 1_000_000)['payroll_tax'])->toBe(30_000)
        // 3 % × $333.33 = 9.9999 → 10.00
        ->and(contributions(52_465, 30, state: 'JAL', taxable: 33_333)['payroll_tax'])->toBe(1_000);
});

it('marks work_risk and payroll_tax as not calculated without premium or state', function (): void {
    $lines = EmployerContributions::forPerson(52_465, 30, fiscal2026(), null, null, 1_000_000);
    $flags = [];

    foreach ($lines as $line) {
        $flags[$line->key] = $line->calculated;
    }

    expect($flags['work_risk'])->toBeFalse()
        ->and($flags['payroll_tax'])->toBeFalse()
        ->and(array_filter($flags, fn (bool $calculated): bool => ! $calculated))->toHaveCount(2)
        ->and(contributions(52_465, 30, taxable: 1_000_000)['work_risk'])->toBe(0)
        ->and(contributions(52_465, 30, taxable: 1_000_000)['payroll_tax'])->toBe(0);
});

it('marks payroll_tax as not calculated for a state without a flat rate', function (): void {
    // Sinaloa has a progressive tariff, so the snapshot has no flat rate for it.
    $lines = EmployerContributions::forPerson(52_465, 30, fiscal2026(), null, 'SIN', 1_000_000);
    $tax = array_values(array_filter($lines, fn (ContributionLine $line): bool => $line->key === 'payroll_tax'))[0];

    expect($tax->cents)->toBe(0)->and($tax->calculated)->toBeFalse();
});

it('is zero on every line for zero days', function (): void {
    $lines = EmployerContributions::forPerson(52_465, 0, fiscal2026(), Rate::scaled(54_355, 7), 'JAL', 0);

    expect(array_map(fn (ContributionLine $line): int => $line->cents, $lines))->each->toBe(0)
        // With a premium and a state the lines count as calculated even when they are zero.
        ->and(array_map(fn (ContributionLine $line): bool => $line->calculated, $lines))->each->toBeTrue();
});

it('adds up exactly: the sum of the lines is the sum of each rounded line', function (): void {
    $lines = contributions(52_465, 30, premium: null, state: 'JAL', taxable: 1_000_000);

    // 376,750 from the nine IMSS and Infonavit lines of the first case, plus 30,000 of payroll tax.
    expect(array_sum($lines))->toBe(406_750);
});
