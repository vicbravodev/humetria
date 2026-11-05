<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support\Calculation;

use App\Modules\Compensation\Data\Assumptions;
use App\Modules\Compensation\Data\FiscalSnapshot;
use App\Modules\Compensation\Data\IndividualSalary;
use App\Modules\Compensation\Data\ProjectionYear;
use App\Modules\Compensation\Data\ScenarioInput;
use App\Modules\Compensation\Enums\PayGroup;
use App\Modules\Organization\Data\CompensationPerson;
use App\Modules\Organization\Data\HeadcountCell;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * The cost of a scenario over 3 to 5 years, year by year: what the company pays without the change and with it.
 *
 * The first year is the scenario's first-year cost. Each later year is that same cost with the scenario moved one more year
 * on, so the tenure, the vacation days, the premium and the SBC grow by themselves, and the fiscal values of that year rule
 * (the employer rates change by year; the UMA and the minimum wage the platform does not know yet grow with the inflation).
 * What the assumptions add, from the second year: every salary grows by the future revisions, on both sides; the proposal's raise
 * (and its fixed amount) stays and grows by the following years rate, on its side only; each year a share of the people leave and are replaced by
 * people of no seniority (the share who stay is the compound of the turnover); and the headcount grows.
 *
 * ponytail: the replacements are costed at no seniority in every year, and the individual salaries leave and are replaced like
 * the rest, at their own salary. A new hire's own ladder (a year later they have a year) and a different pay for a new hire
 * would need the hires of every year as cells of their own.
 */
final class Projection
{
    public const int MIN_HORIZON = 3;

    public const int MAX_HORIZON = 5;

    /**
     * @param  int  $horizon  years, 3 to 5
     * @param  callable(CarbonImmutable): FiscalSnapshot  $fiscalAt  the platform's fiscal values on a date, asked for each year after the first
     * @return list<ProjectionYear>
     */
    public static function years(ScenarioInput $in, Assumptions $assumptions, int $horizon, callable $fiscalAt): array
    {
        if ($horizon < self::MIN_HORIZON || $horizon > self::MAX_HORIZON) {
            throw new InvalidArgumentException('The horizon is '.self::MIN_HORIZON.' to '.self::MAX_HORIZON.' years.');
        }

        $first = ScenarioCost::firstYear($in);
        $years = [new ProjectionYear(1, $in->effectiveOn, $first->beforeCents, $first->afterCents, $first->retroactiveCents, $first->people, $first->staleKeys)];

        for ($year = 2; $year <= $horizon; $year++) {
            $years[] = self::later($in, $assumptions, $year, $fiscalAt);
        }

        return $years;
    }

    /** @param  callable(CarbonImmutable): FiscalSnapshot  $fiscalAt */
    private static function later(ScenarioInput $in, Assumptions $assumptions, int $year, callable $fiscalAt): ProjectionYear
    {
        $passed = $year - 1;
        $on = $in->effectiveOn->addYearsNoOverflow($passed);
        $fiscal = $fiscalAt($on)->indexed(self::compound($assumptions->inflationBp, $passed));
        $stays = self::inputFor($in, $assumptions, $passed, $on, $fiscal, replaced: false);
        $cost = ScenarioCost::firstYear($stays);
        $before = $cost->beforeCents;
        $after = $cost->afterCents;

        if ($assumptions->turnoverBp > 0) {
            $stayed = self::compound(-$assumptions->turnoverBp, $passed);
            $new = ScenarioCost::firstYear(self::inputFor($in, $assumptions, $passed, $on, $fiscal, replaced: true));
            $before = $stayed->of($before) + Rate::one()->minus($stayed)->of($new->beforeCents);
            $after = $stayed->of($after) + Rate::one()->minus($stayed)->of($new->afterCents);
        }

        $growth = self::compound($assumptions->headcountGrowthBp, $passed);

        return new ProjectionYear($year, $on, $growth->of($before), $growth->of($after), 0, $growth->of($cost->people), $cost->staleKeys);
    }

    /**
     * The scenario as it stands `$passed` years later: salaries after the future revisions, the proposal's raise after the
     * following years rate, and, for the people who left, everyone hired on the first day of the month.
     */
    private static function inputFor(ScenarioInput $in, Assumptions $assumptions, int $passed, CarbonImmutable $on, FiscalSnapshot $fiscal, bool $replaced): ScenarioInput
    {
        $revisions = self::compound($assumptions->futureRevisionsBp, $passed);
        $following = self::compound($assumptions->followingYearsRateBp, $passed);
        $raises = [];
        $amounts = [];

        // Each group's change resolved, so the trusted who copy the unionized keep doing it once both have a raise.
        foreach (PayGroup::cases() as $group) {
            $raises[$group->value] = Rate::one()->plus(ScenarioCost::raiseFor($group, $in) ?? Rate::zero())->times($following)->minus(Rate::one());
            $amounts[$group->value] = ScenarioCost::amountFor($group, $in);
        }

        return new ScenarioInput(
            $on,
            0,
            $fiscal,
            array_map(fn (HeadcountCell $cell): HeadcountCell => $replaced
                ? new HeadcountCell($cell->jobGradeId, $cell->areaId, $cell->group, $cell->state, $on->startOfMonth(), $cell->count)
                : $cell, $in->cells),
            array_map(fn (IndividualSalary $individual): IndividualSalary => new IndividualSalary(
                $replaced ? self::hiredOn($individual->person, $on) : $individual->person,
                $revisions->of($individual->dailySalaryCents),
            ), $in->individuals),
            array_map($revisions->of(...), $in->gradeSalaries),
            $in->benefits,
            $raises,
            $in->proposed,
            $in->workRiskPremium,
            $in->defaultState,
            $in->gaps,
            $amounts,
        );
    }

    private static function hiredOn(CompensationPerson $person, CarbonImmutable $on): CompensationPerson
    {
        return new CompensationPerson($person->employeeNumber, $person->jobGradeId, $person->areaId, $person->group, $person->state, $on);
    }

    /** (1 + rate)^years, with the rate in basis points (negative for a loss). */
    private static function compound(int $basisPoints, int $years): Rate
    {
        $factor = Rate::one()->plus(Rate::scaled($basisPoints, 4));
        $result = Rate::one();

        for ($year = 0; $year < $years; $year++) {
            $result = $result->times($factor);
        }

        return $result;
    }
}
