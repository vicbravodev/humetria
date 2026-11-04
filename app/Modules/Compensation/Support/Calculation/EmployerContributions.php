<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support\Calculation;

use App\Modules\Compensation\Data\ContributionLine;
use App\Modules\Compensation\Data\FiscalSnapshot;

/**
 * The employer's contributions for a period of calendar days, for one person with a daily SBC. Every rate comes from the
 * snapshot of the date; each line is settled on its own to the nearest cent (Money), so the total is the exact sum of
 * the lines. The SBC is floored at the minimum wage and capped at 25 UMA for IMSS and Infonavit (LSS art. 28).
 */
final class EmployerContributions
{
    /**
     * @param  ?Rate  $workRiskPremium  the account's premium (decision 9); null leaves work_risk uncalculated
     * @param  ?string  $state  where the work is done; null, or a state without a flat rate, leaves payroll_tax uncalculated
     * @param  int  $payrollTaxableCents  the payroll tax base of the period, which the caller builds from what the tax includes
     * @return list<ContributionLine>
     */
    public static function forPerson(int $sbcCents, int $days, FiscalSnapshot $fiscal, ?Rate $workRiskPremium, ?string $state, int $payrollTaxableCents): array
    {
        $sbc = min(max($sbcCents, $fiscal->minimumWage()), $fiscal->sbcCap());
        $sbcDays = $sbc * $days;
        $surplusBase = max(0, $sbc - $fiscal->rate('imss_surplus_umas')->of($fiscal->uma()));
        $stateRate = $state === null ? null : $fiscal->payrollTax($state);

        return [
            new ContributionLine('imss_fixed', $fiscal->rate('imss_fixed')->of($fiscal->uma() * $days), true),
            new ContributionLine('imss_surplus', $fiscal->rate('imss_surplus')->of($surplusBase * $days), true),
            new ContributionLine('imss_pensioners', $fiscal->rate('imss_pensioners')->of($sbcDays), true),
            new ContributionLine('imss_cash', $fiscal->rate('imss_cash')->of($sbcDays), true),
            new ContributionLine('imss_disability_life', $fiscal->rate('imss_disability_life')->of($sbcDays), true),
            new ContributionLine('work_risk', $workRiskPremium?->of($sbcDays) ?? 0, $workRiskPremium !== null),
            new ContributionLine('imss_childcare', $fiscal->rate('imss_childcare')->of($sbcDays), true),
            new ContributionLine('retirement', $fiscal->rate('retirement')->of($sbcDays), true),
            new ContributionLine('severance_old_age', $fiscal->severanceOldAgeRate($sbc)->of($sbcDays), true),
            new ContributionLine('infonavit', $fiscal->rate('infonavit')->of($sbcDays), true),
            new ContributionLine('payroll_tax', $stateRate?->of($payrollTaxableCents) ?? 0, $stateRate !== null),
        ];
    }
}
