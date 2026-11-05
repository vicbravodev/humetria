<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support\Calculation;

use App\Modules\Compensation\Data\ScenarioInput;
use App\Modules\Compensation\Data\SensitivityResult;
use App\Modules\Compensation\Enums\PayGroup;

/** The cost of a point of salary and of the proposal one point lower and one point higher (its fixed amount kept). */
final class Sensitivity
{
    public static function around(ScenarioInput $in): SensitivityResult
    {
        $point = Rate::scaled(1, 2);
        $lower = [];
        $higher = [];
        $flat = [];
        $amounts = [];

        foreach (PayGroup::cases() as $group) {
            $amounts[$group->value] = ScenarioCost::amountFor($group, $in);
            $raise = ScenarioCost::raiseFor($group, $in) ?? Rate::zero();
            $higher[$group->value] = $raise->plus($point);
            $lower[$group->value] = $raise->compareTo($point) > 0 ? $raise->minus($point) : Rate::zero();
            $flat[$group->value] = $point;
        }

        return new SensitivityResult(
            onePointCents: self::cost($in->withChanges($flat, [])),
            baseCents: self::cost($in),
            minusOneCents: self::cost($in->withChanges($lower, $in->proposed, $amounts)),
            plusOneCents: self::cost($in->withChanges($higher, $in->proposed, $amounts)),
        );
    }

    /** What a point of salary to one group alone costs over the 12 months, on the contract as it is, without retroactivity. */
    public static function pointFor(ScenarioInput $in, PayGroup $group): int
    {
        $raises = [];

        foreach (PayGroup::cases() as $other) {
            $raises[$other->value] = $other === $group ? Rate::scaled(1, 2) : Rate::zero();
        }

        return ScenarioCost::firstYear($in->withChanges($raises, []))->deltaCents();
    }

    private static function cost(ScenarioInput $in): int
    {
        return ScenarioCost::firstYear($in)->firstYearCents();
    }
}
