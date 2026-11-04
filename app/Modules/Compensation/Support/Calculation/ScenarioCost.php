<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support\Calculation;

use App\Modules\Compensation\Data\BenefitSpec;
use App\Modules\Compensation\Data\ConceptCost;
use App\Modules\Compensation\Data\CostBreakdown;
use App\Modules\Compensation\Data\CostCut;
use App\Modules\Compensation\Data\CostSubject;
use App\Modules\Compensation\Data\CostUnit;
use App\Modules\Compensation\Data\MonthlyCost;
use App\Modules\Compensation\Data\ScenarioInput;
use App\Modules\Compensation\Data\SideCost;
use App\Modules\Compensation\Data\UnitCost;
use App\Modules\Compensation\Enums\BaseBenefit;
use App\Modules\Compensation\Enums\CostConcept;
use App\Modules\Compensation\Enums\PayGroup;
use Illuminate\Support\Str;

/**
 * What a scenario costs the company in its first 12 months, before and after the change.
 *
 * For each cost unit (a headcount cell or a person with an individual salary) and each of the 12 months counted from the
 * effective date, the person is costed as if that month lasted a whole year: salary, benefits and employer contributions of
 * 365 days, multiplied by the people in the unit. Tenure is read on the day each month starts, so the vacation days, the
 * premium and the SBC step on the anniversary. A cell stands for the 1st of its month of hire (the roster does not say more
 * than the month); an individual has their exact date. The snapshot of the effective date rules all 12 months.
 *
 * Rounding: each amount is settled to the cent for one person and one month; then each concept of a unit is spread over the
 * 12 months by MonthlySplit (a twelfth of each month's full-year amount, the year settled once, the months adding up to it).
 * Everything else (the concepts, the months, the cuts) is a plain sum of those, so every total is exact. Retroactive months
 * cost what the first month's change costs. A new hire in the 12 months is not modelled, and the aguinaldo is a full year's.
 */
final class ScenarioCost
{
    private const int MONTHS = 12;

    private const int DAYS_IN_YEAR = 365;

    public static function firstYear(ScenarioInput $in): CostBreakdown
    {
        $afterBenefits = self::benefitsAfter($in);
        $results = [];
        $uncosted = [];
        $cache = ['before' => [], 'after' => []];

        foreach (self::units($in) as $unit) {
            $salary = $unit->dailySalaryCents;

            if ($salary === null) {
                $uncosted[$unit->jobGradeId] = ($uncosted[$unit->jobGradeId] ?? 0) + $unit->count;

                continue;
            }

            $results[] = new UnitCost(
                $unit,
                self::side($unit, $salary, $in->benefits, $in, $cache['before']),
                self::side($unit, self::raised($salary, $unit->group, $in), $afterBenefits, $in, $cache['after']),
            );
        }

        $ledger = self::ledger($results);
        $concepts = self::concepts($ledger, $in->retroMonths);

        return new CostBreakdown(
            beforeCents: array_sum(array_map(fn (ConceptCost $concept): int => $concept->beforeCents, $concepts)),
            afterCents: array_sum(array_map(fn (ConceptCost $concept): int => $concept->afterCents, $concepts)),
            recurringCents: array_sum(array_map(fn (ConceptCost $concept): int => $concept->recurringCents, $concepts)),
            retroactiveCents: array_sum(array_map(fn (ConceptCost $concept): int => $concept->retroactiveCents, $concepts)),
            people: array_sum(array_map(fn (UnitCost $result): int => $result->unit->count, $results)),
            concepts: $concepts,
            months: self::months($ledger, $in),
            byArea: self::cuts($results, fn (CostUnit $unit): ?string => $unit->areaId),
            byGrade: self::cuts($results, fn (CostUnit $unit): string => $unit->jobGradeId),
            byGroup: self::cuts($results, fn (CostUnit $unit): string => $unit->group->value),
            uncostedGrades: $uncosted,
            uncalculatedLines: self::uncalculated($results, $concepts),
            staleKeys: $in->fiscal->staleKeys(),
            gaps: $in->gaps,
        );
    }

    /** @return list<BenefitSpec> the current benefits, each replaced by the proposed one with its key, plus the new ones */
    private static function benefitsAfter(ScenarioInput $in): array
    {
        $proposed = array_map(fn (BenefitSpec $benefit): string => $benefit->key, $in->proposed);
        $kept = array_filter($in->benefits, fn (BenefitSpec $benefit): bool => ! in_array($benefit->key, $proposed, true));

        return [...$kept, ...$in->proposed];
    }

    /** @return list<CostUnit> */
    private static function units(ScenarioInput $in): array
    {
        $units = [];

        foreach ($in->cells as $cell) {
            $units[] = new CostUnit(
                $cell->jobGradeId, $cell->areaId, PayGroup::from($cell->group->value), $cell->state, $cell->hireMonth, $cell->count,
                $in->gradeSalaries[$cell->jobGradeId] ?? null,
            );
        }

        foreach ($in->individuals as $individual) {
            $person = $individual->person;
            $units[] = new CostUnit(
                $person->jobGradeId, $person->areaId, PayGroup::from($person->group->value), $person->state, $person->hiredOn, 1,
                $individual->dailySalaryCents,
            );
        }

        return $units;
    }

    /** The daily salary after the raise of the group, settled to the cent; the trusted take the unionized raise if they have none. */
    private static function raised(int $salary, PayGroup $group, ScenarioInput $in): int
    {
        $raise = $in->raises[$group->value]
            ?? ($group === PayGroup::Trusted ? ($in->raises[PayGroup::Unionized->value] ?? null) : null);

        return $raise === null ? $salary : Rate::one()->plus($raise)->of($salary);
    }

    /**
     * @param  list<BenefitSpec>  $benefits
     * @param  array<string, array{lines: array<string, int>, uncalculated: list<string>}>  $cache  the persons already costed on this side
     */
    private static function side(CostUnit $unit, int $salary, array $benefits, ScenarioInput $in, array &$cache): SideCost
    {
        $annualised = [];
        $uncalculated = [];

        for ($month = 0; $month < self::MONTHS; $month++) {
            $on = $in->effectiveOn->addMonthsNoOverflow($month);
            $subject = new CostSubject($salary, $unit->group, $unit->jobGradeId, TenureYears::at($unit->hiredOn, $on), TenureYears::monthsAt($unit->hiredOn, $on));
            $state = $unit->state ?? $in->defaultState;
            // Two people cost the same when they share the salary, the group, the state, the years of service and which
            // benefits are theirs (a minimum tenure can start inside the year without a new year of service).
            $applying = implode('', array_map(fn (BenefitSpec $benefit): string => BenefitCost::applies($benefit, $subject) ? '1' : '0', $benefits));
            $key = "{$salary}|{$unit->group->value}|{$state}|{$subject->tenureYears}|{$applying}";
            $person = $cache[$key] ??= self::person($subject, $benefits, $in, $state);

            foreach ($person['lines'] as $id => $cents) {
                $annualised[$id][] = $cents * $unit->count;
            }

            $uncalculated = [...$uncalculated, ...$person['uncalculated']];
        }

        return new SideCost(
            array_map(MonthlySplit::of(...), $annualised),
            array_map(fn (array $year): int => $year[0], $annualised),
            array_values(array_unique($uncalculated)),
        );
    }

    /**
     * One person for a full year on one day, by concept id: "salary:salary", "benefit:{key}", "contribution:{line}".
     *
     * @param  list<BenefitSpec>  $benefits
     * @return array{lines: array<string, int>, uncalculated: list<string>}
     */
    private static function person(CostSubject $subject, array $benefits, ScenarioInput $in, ?string $state): array
    {
        $salary = $subject->dailySalaryCents;
        $terms = BenefitCost::lawTerms($benefits, $subject, $in->fiscal);
        $aguinaldo = $terms->aguinaldoCost($salary);
        $premium = $terms->premiumCost($salary);
        $lines = ['salary:salary' => $salary * self::DAYS_IN_YEAR, 'benefit:christmas_bonus' => $aguinaldo, 'benefit:vacation_premium' => $premium];
        $taxable = $lines['salary:salary'] + ($terms->aguinaldoTaxable ? $aguinaldo : 0) + ($terms->premiumTaxable ? $premium : 0);
        $integrating = Rate::zero();

        foreach ($benefits as $benefit) {
            // The aguinaldo and the premium are costed from the terms above, and the vacation days are paid in the salary.
            if (in_array($benefit->key, [BaseBenefit::ChristmasBonus->value, BaseBenefit::Vacation->value, BaseBenefit::VacationPremium->value], true)) {
                continue;
            }

            $annual = BenefitCost::annual($benefit, $subject, $in->fiscal);
            $lines["benefit:{$benefit->key}"] = $annual;
            $taxable += $benefit->inPayrollTax ? $annual : 0;
            $integrating = $integrating->plus(BenefitCost::dailyIntegrating($benefit, $subject, $in->fiscal));
        }

        $sbc = SalaryBase::sbc($salary, $terms->factor(), $in->fiscal->sbcCap(), $integrating);
        $uncalculated = [];

        foreach (EmployerContributions::forPerson($sbc, self::DAYS_IN_YEAR, $in->fiscal, $in->workRiskPremium, $state, $taxable) as $line) {
            $lines["contribution:{$line->key}"] = $line->cents;

            if (! $line->calculated) {
                $uncalculated[] = $line->key;
            }
        }

        return ['lines' => $lines, 'uncalculated' => $uncalculated];
    }

    /**
     * The sum over all units of each concept: its 12 months before and after, and what changes at the first month, as a year.
     * Concepts come in the order salary, benefits, contributions, each in the order they first appear.
     *
     * @param  list<UnitCost>  $results
     * @return array<string, array{before: list<int>, after: list<int>, recurring: int}>
     */
    private static function ledger(array $results): array
    {
        $ids = [];

        foreach ($results as $result) {
            foreach ([...array_keys($result->before->months), ...array_keys($result->after->months)] as $id) {
                $ids[$id] = true;
            }
        }

        $ids = array_keys($ids);
        usort($ids, fn (string $a, string $b): int => self::rank($a) <=> self::rank($b));
        $ledger = [];

        foreach ($ids as $id) {
            $ledger[$id] = [
                'before' => self::sumMonths(array_map(fn (UnitCost $result): array => $result->before->months[$id] ?? self::noMonths(), $results)),
                'after' => self::sumMonths(array_map(fn (UnitCost $result): array => $result->after->months[$id] ?? self::noMonths(), $results)),
                'recurring' => array_sum(array_map(fn (UnitCost $result): int => ($result->after->month0[$id] ?? 0) - ($result->before->month0[$id] ?? 0), $results)),
            ];
        }

        return $ledger;
    }

    private static function rank(string $id): int
    {
        return match (CostConcept::from(Str::before($id, ':'))) {
            CostConcept::Salary => 0,
            CostConcept::Benefit => 1,
            CostConcept::Contribution => 2,
        };
    }

    /**
     * @param  array<string, array{before: list<int>, after: list<int>, recurring: int}>  $ledger
     * @return list<ConceptCost>
     */
    private static function concepts(array $ledger, int $retroMonths): array
    {
        $concepts = [];

        foreach ($ledger as $id => $row) {
            $concepts[] = new ConceptCost(
                CostConcept::from(Str::before($id, ':')),
                Str::after($id, ':'),
                array_sum($row['before']),
                array_sum($row['after']),
                $row['recurring'],
                $retroMonths * ($row['after'][0] - $row['before'][0]),
            );
        }

        return $concepts;
    }

    /**
     * @param  array<string, array{before: list<int>, after: list<int>, recurring: int}>  $ledger
     * @return list<MonthlyCost>
     */
    private static function months(array $ledger, ScenarioInput $in): array
    {
        $before = self::sumMonths(array_column($ledger, 'before'));
        $after = self::sumMonths(array_column($ledger, 'after'));
        $months = [];

        for ($month = 0; $month < self::MONTHS; $month++) {
            $months[] = new MonthlyCost($in->effectiveOn->addMonthsNoOverflow($month), $before[$month], $after[$month]);
        }

        return $months;
    }

    /**
     * @param  list<UnitCost>  $results
     * @param  callable(CostUnit): ?string  $keyOf
     * @return list<CostCut>
     */
    private static function cuts(array $results, callable $keyOf): array
    {
        $slices = [];

        foreach ($results as $result) {
            $key = $keyOf($result->unit);
            $id = $key ?? '';
            $slices[$id] ??= ['key' => $key, 'people' => 0, 'before' => 0, 'after' => 0];
            $slices[$id]['people'] += $result->unit->count;
            $slices[$id]['before'] += $result->before->yearCents();
            $slices[$id]['after'] += $result->after->yearCents();
        }

        ksort($slices);

        return array_values(array_map(fn (array $slice): CostCut => new CostCut($slice['key'], $slice['people'], $slice['before'], $slice['after']), $slices));
    }

    /**
     * @param  list<UnitCost>  $results
     * @param  list<ConceptCost>  $concepts
     * @return list<string> the lines that at least one unit could not calculate, in the order of the cascade
     */
    private static function uncalculated(array $results, array $concepts): array
    {
        $missing = [];

        foreach ($results as $result) {
            foreach ([...$result->before->uncalculated, ...$result->after->uncalculated] as $line) {
                $missing[$line] = true;
            }
        }

        $lines = [];

        foreach ($concepts as $concept) {
            if ($concept->type === CostConcept::Contribution && isset($missing[$concept->key])) {
                $lines[] = $concept->key;
            }
        }

        return $lines;
    }

    /**
     * The sum of lists of 12 months, month by month.
     *
     * @param  list<list<int>>  $rows
     * @return list<int>
     */
    private static function sumMonths(array $rows): array
    {
        if ($rows === []) {
            return self::noMonths();
        }

        return array_map(fn (int ...$column): int => array_sum($column), ...$rows);
    }

    /** @return list<int> */
    private static function noMonths(): array
    {
        return array_fill(0, self::MONTHS, 0);
    }
}
