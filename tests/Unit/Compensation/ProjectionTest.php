<?php

declare(strict_types=1);

use App\Modules\Compensation\Data\Assumptions;
use App\Modules\Compensation\Data\FiscalSnapshot;
use App\Modules\Compensation\Data\ProjectionYear;
use App\Modules\Compensation\Data\ScenarioInput;
use App\Modules\Compensation\Support\Calculation\Projection;
use App\Modules\Compensation\Support\Calculation\Rate;
use App\Modules\Compensation\Support\Calculation\ScenarioCost;
use App\Modules\Organization\Enums\StaffGroup;
use Carbon\CarbonImmutable;

mutates(Projection::class);

/**
 * @param  array<string, mixed>  $with  scenarioInput's options
 * @return list<ProjectionYear>
 */
function project(array $with = [], ?Assumptions $assumptions = null, int $horizon = 3, ?Closure $fiscalAt = null): array
{
    return Projection::years(scenarioInput($with), $assumptions ?? Assumptions::none(), $horizon, $fiscalAt ?? fn (CarbonImmutable $on): FiscalSnapshot => scenarioFiscal());
}

/** The cost of one year computed alone: the scenario moved to that effective date, with what the projection would hold. */
function alone(array $with): ProjectionYear
{
    $cost = ScenarioCost::firstYear(scenarioInput($with));

    return new ProjectionYear(1, CarbonImmutable::parse($with['effectiveOn']), $cost->beforeCents, $cost->afterCents, 0, $cost->people, []);
}

it('shows every year of the horizon, the first one as the first-year cost', function (): void {
    $years = project(['retroMonths' => 3, 'raises' => ['unionized' => Rate::scaled(5, 2)]], horizon: 5);
    $first = ScenarioCost::firstYear(scenarioInput(['retroMonths' => 3, 'raises' => ['unionized' => Rate::scaled(5, 2)]]));

    expect($years)->toHaveCount(5)->each->toBeInstanceOf(ProjectionYear::class)
        ->and(array_map(fn (ProjectionYear $year): int => $year->year, $years))->toBe([1, 2, 3, 4, 5])
        ->and(array_map(fn (ProjectionYear $year): string => $year->effectiveOn->toDateString(), $years))
        ->toBe(['2026-01-01', '2027-01-01', '2028-01-01', '2029-01-01', '2030-01-01'])
        ->and($years[0]->beforeCents)->toBe($first->beforeCents)
        ->and($years[0]->afterCents)->toBe($first->afterCents)
        ->and($years[0]->retroactiveCents)->toBe($first->retroactiveCents)->and($years[0]->retroactiveCents)->toBeGreaterThan(0)
        ->and($years[1]->retroactiveCents)->toBe(0)
        ->and($years[0]->deltaCents())->toBe($first->deltaCents())
        ->and($years[0]->costCents())->toBe($first->firstYearCents());
});

it('rejects a horizon of 2 or 6 and accepts 3 and 5', function (): void {
    foreach ([2, 6, 0, -1] as $horizon) {
        expect(fn () => project(horizon: $horizon))->toThrow(InvalidArgumentException::class);
    }

    expect(project(horizon: 3))->toHaveCount(3)->and(project(horizon: 5))->toHaveCount(5);
});

it('grows tenure by itself and shows each year', function (): void {
    $years = project(['raises' => ['unionized' => Rate::scaled(5, 2)]], horizon: 4);

    // The person has 1 year of service in 2026 (12 days of vacation), 2 in 2027 (14), 3 in 2028 (16) and 4 in 2029 (18).
    foreach ([2 => '2027-01-01', 3 => '2028-01-01', 4 => '2029-01-01'] as $number => $on) {
        $expected = alone(['effectiveOn' => $on, 'raises' => ['unionized' => Rate::scaled(5, 2)]]);

        expect($years[$number - 1]->beforeCents)->toBe($expected->beforeCents)
            ->and($years[$number - 1]->afterCents)->toBe($expected->afterCents);
    }

    expect($years[1]->beforeCents)->toBeGreaterThan($years[0]->beforeCents);
});

it('keeps the raise of the proposal in the following years, on the same salary the contract would reach', function (): void {
    $years = project(['raises' => ['unionized' => Rate::scaled(5, 2)]], horizon: 3);

    expect($years[1]->deltaCents())->toBeGreaterThan(0)
        ->and($years[2]->afterCents - $years[2]->beforeCents)->toBeGreaterThan(0);
});

it('applies the future revisions to both sides from the second year', function (): void {
    $assumptions = new Assumptions(inflationBp: 0, turnoverBp: 0, headcountGrowthBp: 0, futureRevisionsBp: 500, followingYearsRateBp: 0);
    $years = project(['raises' => ['unionized' => Rate::scaled(5, 2)]], $assumptions);

    // $500 → $525 in 2027 and → $551.25 in 2028 (settled to the cent each time), both without and with the proposal's raise.
    $second = alone(['effectiveOn' => '2027-01-01', 'gradeSalaries' => ['g1' => 52_500], 'raises' => ['unionized' => Rate::scaled(5, 2)]]);
    $third = alone(['effectiveOn' => '2028-01-01', 'gradeSalaries' => ['g1' => 55_125], 'raises' => ['unionized' => Rate::scaled(5, 2)]]);

    expect($years[1]->beforeCents)->toBe($second->beforeCents)->and($years[1]->afterCents)->toBe($second->afterCents)
        ->and($years[2]->beforeCents)->toBe($third->beforeCents)->and($years[2]->afterCents)->toBe($third->afterCents);
});

it('applies the future revisions to an individual salary too', function (): void {
    $assumptions = new Assumptions(inflationBp: 0, turnoverBp: 0, headcountGrowthBp: 0, futureRevisionsBp: 1000, followingYearsRateBp: 0);
    $with = ['cells' => [], 'individuals' => [scenarioPerson('E-1', 80_000)]];
    $years = project($with, $assumptions);

    $second = alone([...$with, 'effectiveOn' => '2027-01-01', 'individuals' => [scenarioPerson('E-1', 88_000)]]);

    expect($years[1]->beforeCents)->toBe($second->beforeCents);
});

it('adds the following years rate to the proposal side only', function (): void {
    $assumptions = new Assumptions(inflationBp: 0, turnoverBp: 0, headcountGrowthBp: 0, futureRevisionsBp: 0, followingYearsRateBp: 300);
    $years = project(['raises' => ['unionized' => Rate::scaled(5, 2)]], $assumptions);

    // 1.05 × 1.03 − 1 = 8.15 % in 2027 and 1.05 × 1.03² − 1 = 11.4 % (rounded to the cent of the salary) in 2028.
    $second = alone(['effectiveOn' => '2027-01-01', 'raises' => ['unionized' => Rate::scaled(815, 4)]]);

    expect($years[1]->beforeCents)->toBe($second->beforeCents)
        ->and($years[1]->afterCents)->toBe($second->afterCents)
        ->and($years[2]->afterCents)->toBeGreaterThan($years[1]->afterCents);
});

it('gives the following years rate to a scenario with no raise of its own', function (): void {
    $assumptions = new Assumptions(inflationBp: 0, turnoverBp: 0, headcountGrowthBp: 0, futureRevisionsBp: 0, followingYearsRateBp: 300);
    $years = project([], $assumptions);
    $second = alone(['effectiveOn' => '2027-01-01', 'raises' => ['unionized' => Rate::scaled(3, 2), 'trusted' => Rate::scaled(3, 2)]]);

    expect($years[0]->deltaCents())->toBe(0)->and($years[1]->afterCents)->toBe($second->afterCents);
});

it('grows the headcount by the assumed rate each year', function (): void {
    $assumptions = new Assumptions(inflationBp: 0, turnoverBp: 0, headcountGrowthBp: 1000, futureRevisionsBp: 0, followingYearsRateBp: 0);
    $with = ['cells' => [scenarioCell(10)], 'raises' => ['unionized' => Rate::scaled(5, 2)]];
    $years = project($with, $assumptions);
    $second = alone([...$with, 'effectiveOn' => '2027-01-01']);
    $third = alone([...$with, 'effectiveOn' => '2028-01-01']);

    expect($years[1]->people)->toBe(11)->and($years[2]->people)->toBe(12)
        ->and($years[1]->beforeCents)->toBe(Rate::scaled(11, 1)->of($second->beforeCents))
        ->and($years[2]->afterCents)->toBe(Rate::scaled(121, 2)->of($third->afterCents));
});

it('replaces the people who leave with new hires, who start with no years of service', function (): void {
    $assumptions = new Assumptions(inflationBp: 0, turnoverBp: 2000, headcountGrowthBp: 0, futureRevisionsBp: 0, followingYearsRateBp: 0);
    $with = ['cells' => [scenarioCell(10, '2015-01-01')], 'raises' => ['unionized' => Rate::scaled(5, 2)]];
    $years = project($with, $assumptions);

    $stay = alone([...$with, 'effectiveOn' => '2027-01-01']);
    $new = alone(['effectiveOn' => '2027-01-01', 'cells' => [scenarioCell(10, '2027-01-01')], 'raises' => ['unionized' => Rate::scaled(5, 2)]]);

    // 80 % are still there with 12 years of service; 20 % are new hires on the 1st of January.
    expect($years[1]->beforeCents)->toBe(Rate::scaled(8, 1)->of($stay->beforeCents) + Rate::scaled(2, 1)->of($new->beforeCents))
        ->and($years[1]->afterCents)->toBe(Rate::scaled(8, 1)->of($stay->afterCents) + Rate::scaled(2, 1)->of($new->afterCents))
        ->and($years[1]->beforeCents)->toBeLessThan($stay->beforeCents);
});

it('compounds the turnover: 64 percent are left after two years', function (): void {
    $assumptions = new Assumptions(inflationBp: 0, turnoverBp: 2000, headcountGrowthBp: 0, futureRevisionsBp: 0, followingYearsRateBp: 0);
    $with = ['cells' => [scenarioCell(10, '2015-01-01')]];
    $years = project($with, $assumptions);

    $stay = alone([...$with, 'effectiveOn' => '2028-01-01']);
    $new = alone(['effectiveOn' => '2028-01-01', 'cells' => [scenarioCell(10, '2028-01-01')]]);

    expect($years[2]->beforeCents)->toBe(Rate::scaled(64, 2)->of($stay->beforeCents) + Rate::scaled(36, 2)->of($new->beforeCents));
});

it('treats a person with an individual salary who leaves like the rest', function (): void {
    $assumptions = new Assumptions(inflationBp: 0, turnoverBp: 5000, headcountGrowthBp: 0, futureRevisionsBp: 0, followingYearsRateBp: 0);
    $with = ['cells' => [], 'individuals' => [scenarioPerson('E-1', 80_000, '2015-01-01')]];
    $years = project($with, $assumptions);

    $stay = alone([...$with, 'effectiveOn' => '2027-01-01']);
    $new = alone(['effectiveOn' => '2027-01-01', 'cells' => [], 'individuals' => [scenarioPerson('E-1', 80_000, '2027-01-01')]]);

    expect($years[1]->beforeCents)->toBe(Rate::scaled(5, 1)->of($stay->beforeCents) + Rate::scaled(5, 1)->of($new->beforeCents));
});

it('grows the UMA by the inflation only when the platform does not know that year', function (): void {
    $assumptions = new Assumptions(inflationBp: 1000, turnoverBp: 0, headcountGrowthBp: 0, futureRevisionsBp: 0, followingYearsRateBp: 0);
    $with = ['cells' => [scenarioCell(1, '2025-01-01')], 'gradeSalaries' => ['g1' => 400_000]];

    $known = project($with, $assumptions, fiscalAt: fn (CarbonImmutable $on): FiscalSnapshot => scenarioFiscal());
    $carried = project($with, $assumptions, fiscalAt: fn (CarbonImmutable $on): FiscalSnapshot => scenarioFiscal(['uma', 'minimum_wage']));

    $indexed = ScenarioCost::firstYear(new ScenarioInput(
        CarbonImmutable::parse('2027-01-01'), 0, scenarioFiscal(['uma', 'minimum_wage'])->indexed(Rate::scaled(11, 1)), [scenarioCell(1, '2025-01-01')], [], ['g1' => 400_000],
        [], [], [], Rate::scaled(50, 4), 'JAL', scenarioInput($with)->gaps,
    ));

    // A salary of $4,000 a day is over the 25-UMA cap, so a higher UMA changes the contributions; a known UMA does not move.
    expect($carried[1]->beforeCents)->toBe($indexed->beforeCents)
        ->and($known[1]->beforeCents)->toBe(alone([...$with, 'effectiveOn' => '2027-01-01'])->beforeCents)
        ->and($carried[1]->beforeCents)->not->toBe($known[1]->beforeCents);
});

it('compounds the inflation from the first year', function (): void {
    $assumptions = new Assumptions(inflationBp: 1000, turnoverBp: 0, headcountGrowthBp: 0, futureRevisionsBp: 0, followingYearsRateBp: 0);
    $with = ['cells' => [scenarioCell(1, '2025-01-01')], 'gradeSalaries' => ['g1' => 400_000]];
    $years = project($with, $assumptions, fiscalAt: fn (CarbonImmutable $on): FiscalSnapshot => scenarioFiscal(['uma', 'minimum_wage']));

    $indexed = fn (string $on, string $factor): int => ScenarioCost::firstYear(new ScenarioInput(
        CarbonImmutable::parse($on), 0, scenarioFiscal(['uma', 'minimum_wage'])->indexed(Rate::scaled((int) $factor, 2)), [scenarioCell(1, '2025-01-01')], [], ['g1' => 400_000],
        [], [], [], Rate::scaled(50, 4), 'JAL', scenarioInput($with)->gaps,
    ))->beforeCents;

    expect($years[2]->beforeCents)->toBe($indexed('2028-01-01', '121'));
});

it('asks for the fiscal values of each year after the first and flags the stale ones', function (): void {
    $asked = [];
    $years = project(horizon: 4, fiscalAt: function (CarbonImmutable $on) use (&$asked): FiscalSnapshot {
        $asked[] = $on->toDateString();

        return scenarioFiscal($on->year >= 2028 ? ['uma'] : []);
    });

    expect($asked)->toBe(['2027-01-01', '2028-01-01', '2029-01-01'])
        ->and($years[0]->staleKeys)->toBe([])->and($years[1]->staleKeys)->toBe([])
        ->and($years[2]->staleKeys)->toBe(['uma'])->and($years[3]->staleKeys)->toBe(['uma']);
});

it('flags the stale values of the first year too', function (): void {
    $years = project(['fiscal' => scenarioFiscal(['minimum_wage'])]);

    expect($years[0]->staleKeys)->toBe(['minimum_wage']);
});

it('costs nothing, without errors, for an empty roster', function (): void {
    $years = project(['cells' => [], 'raises' => ['unionized' => Rate::scaled(5, 2)]], new Assumptions(300, 500, 500, 300, 100));

    expect($years)->toHaveCount(3);

    foreach ($years as $year) {
        expect($year->beforeCents)->toBe(0)->and($year->afterCents)->toBe(0)->and($year->people)->toBe(0);
    }
});

it('keeps a scenario without changes at zero extra cost every year', function (): void {
    foreach (project(horizon: 5) as $year) {
        expect($year->deltaCents())->toBe(0);
    }
});

it('keeps the fixed amount in the following years, for the trusted who copy it too, and grows it by the following years rate', function (): void {
    $cells = [scenarioCell(), scenarioCell(group: StaffGroup::Trusted)];
    $flat = project(['cells' => $cells, 'amounts' => ['unionized' => 60_800]]);
    // $608 a month on $500 a day is 4 %: the same scenario as a percent costs the same every year.
    $percent = project(['cells' => $cells, 'raises' => ['unionized' => Rate::scaled(4, 2)]]);
    $assumptions = new Assumptions(inflationBp: 0, turnoverBp: 0, headcountGrowthBp: 0, futureRevisionsBp: 0, followingYearsRateBp: 300);
    // (500 + 20) × 1.03 = 535.60 in 2027.
    $grown = project(['cells' => $cells, 'amounts' => ['unionized' => 60_800]], $assumptions);
    $second = alone(['effectiveOn' => '2027-01-01', 'cells' => $cells, 'raises' => ['unionized' => Rate::scaled(712, 4), 'trusted' => Rate::scaled(712, 4)]]);

    expect(array_map(fn (ProjectionYear $year): int => $year->deltaCents(), $flat))->toBe(array_map(fn (ProjectionYear $year): int => $year->deltaCents(), $percent))
        ->and($flat[1]->deltaCents())->toBeGreaterThan(0)
        ->and($grown[1]->afterCents)->toBe($second->afterCents);
});
