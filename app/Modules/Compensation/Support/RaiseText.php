<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support;

use App\Modules\Compensation\Enums\RaiseUnit;
use App\Modules\Compensation\Models\Scenario;
use App\Modules\Compensation\Support\Calculation\Money;

/** What a scenario raises, in words: "4 % sindicalizados · 2.5 % confianza", or one raise for both when the trusted copy it. */
final class RaiseText
{
    public static function of(Scenario $scenario): string
    {
        $unionized = self::value($scenario, $scenario->group_changes['unionized'] ?? 0);
        $trusted = $scenario->group_changes['trusted'] ?? null;
        $amount = $scenario->raise_unit === RaiseUnit::Amount;

        if ($trusted === null) {
            return $amount ? "{$unionized} a sindicalizados y confianza" : "{$unionized} sindicalizados y confianza";
        }

        $other = self::value($scenario, $trusted);

        return $amount ? "{$unionized} a sindicalizados · {$other} a confianza" : "{$unionized} sindicalizados · {$other} confianza";
    }

    /** "4 %" or "$608 al mes". */
    private static function value(Scenario $scenario, int $value): string
    {
        return $scenario->raise_unit === RaiseUnit::Amount ? Money::pesos($value).' al mes' : PercentBasisPoints::format($value).' %';
    }
}
