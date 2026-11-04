<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support\Calculation;

use App\Modules\Compensation\Data\BenefitSpec;
use App\Modules\Compensation\Data\CostSubject;
use App\Modules\Compensation\Data\CostUnit;
use App\Modules\Compensation\Data\ScenarioInput;
use App\Modules\Compensation\Data\SeniorityEstimate;

/**
 * An estimate of the seniority premium (LFT art. 162): 12 days of salary per year of service, the daily salary capped at two
 * minimum wages (arts. 485 and 486), owed on the effective date to everyone with 15 years of service or more. The law owes it
 * to fewer years too (a dismissal, a death), but the 15 years of a voluntary leave is the condition the calculator counts, so
 * the figure is a floor of the liability, not an actuarial valuation (NIF D-3).
 *
 * The salary is the daily salary plus what each benefit marked as part of the seniority base costs per calendar day. The
 * general minimum wage rules: the border zone's is not modelled.
 */
final class SeniorityPremium
{
    /** LFT art. 162 fr. III: a voluntary leave pays it from 15 years of service. */
    public const int MIN_YEARS = 15;

    private const int DAYS_IN_YEAR = 365;

    public static function estimate(ScenarioInput $in): SeniorityEstimate
    {
        $afterBenefits = ScenarioCost::benefitsAfter($in);
        $before = 0;
        $after = 0;
        $people = 0;

        foreach (ScenarioCost::units($in) as $unit) {
            $years = TenureYears::at($unit->hiredOn, $in->effectiveOn);

            if ($unit->dailySalaryCents === null || $years < self::MIN_YEARS) {
                continue;
            }

            $people += $unit->count;
            $before += $unit->count * self::person($unit, $unit->dailySalaryCents, $years, $in->benefits, $in);
            $after += $unit->count * self::person($unit, ScenarioCost::raised($unit->dailySalaryCents, $unit->group, $in), $years, $afterBenefits, $in);
        }

        return new SeniorityEstimate($before, $after, $people);
    }

    /**
     * @param  list<BenefitSpec>  $benefits
     */
    private static function person(CostUnit $unit, int $salary, int $years, array $benefits, ScenarioInput $in): int
    {
        $subject = new CostSubject($salary, $unit->group, $unit->jobGradeId, $years, TenureYears::monthsAt($unit->hiredOn, $in->effectiveOn));
        $base = Rate::scaled($salary, 0);

        foreach ($benefits as $benefit) {
            if ($benefit->inSeniorityBase) {
                $base = $base->plus(Rate::fraction(BenefitCost::annual($benefit, $subject, $in->fiscal), self::DAYS_IN_YEAR));
            }
        }

        $cap = $in->fiscal->rate('seniority_premium_cap_minimum_wages')->times(Rate::scaled($in->fiscal->minimumWage(), 0));
        $capped = $base->compareTo($cap) <= 0 ? $base : $cap;

        return $in->fiscal->rate('seniority_premium_days')->times(Rate::scaled($years, 0))->times($capped)->of(1);
    }
}
