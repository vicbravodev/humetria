<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support\Calculation;

/**
 * The daily salary base of contributions (SBC): the daily salary times the integration factor, truncated to the cent
 * (the plan's rule, see Money) and capped at 25 UMA (LSS art. 28).
 */
final class SalaryBase
{
    /**
     * @param  ?Rate  $extraDaily  other daily cents that integrate (a bonus, the excess of the vouchers), added before the
     *                             cent is truncated so the fraction is dropped once
     */
    public static function sbc(int $dailySalaryCents, Rate $factor, int $sbcCapCents, ?Rate $extraDaily = null): int
    {
        $integrated = $factor->times(Rate::scaled($dailySalaryCents, 0))->plus($extraDaily ?? Rate::zero());

        return min($integrated->truncated(1), $sbcCapCents);
    }
}
