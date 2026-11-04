<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support\Calculation;

/**
 * The daily salary base of contributions (SBC): the daily salary times the integration factor, truncated to the cent
 * (the plan's rule, see Money) and capped at 25 UMA (LSS art. 28).
 */
final class SalaryBase
{
    public static function sbc(int $dailySalaryCents, Rate $factor, int $sbcCapCents): int
    {
        return min($factor->truncated($dailySalaryCents), $sbcCapCents);
    }
}
