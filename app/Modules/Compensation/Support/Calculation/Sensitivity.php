<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support\Calculation;

use App\Modules\Compensation\Data\ScenarioInput;
use App\Modules\Compensation\Data\SensitivityResult;
use App\Modules\Compensation\Enums\PayGroup;

/** The cost of a point of salary and of the proposal one point lower and one point higher. */
final class Sensitivity
{
    public static function around(ScenarioInput $in): SensitivityResult
    {
        $point = Rate::scaled(1, 2);
        $lower = [];
        $higher = [];
        $flat = [];

        foreach (PayGroup::cases() as $group) {
            $raise = ScenarioCost::raiseFor($group, $in) ?? Rate::zero();
            $higher[$group->value] = $raise->plus($point);
            $lower[$group->value] = $raise->compareTo($point) > 0 ? $raise->minus($point) : Rate::zero();
            $flat[$group->value] = $point;
        }

        return new SensitivityResult(
            onePointCents: self::cost($in->withChanges($flat, [])),
            baseCents: self::cost($in),
            minusOneCents: self::cost($in->withChanges($lower, $in->proposed)),
            plusOneCents: self::cost($in->withChanges($higher, $in->proposed)),
        );
    }

    private static function cost(ScenarioInput $in): int
    {
        return ScenarioCost::firstYear($in)->firstYearCents();
    }
}
