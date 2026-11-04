<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support\Calculation;

/**
 * The integration factor of the SBC (LSS art. 27): 1 + aguinaldo / 365 + vacation days × vacation premium / 365, with the
 * person's real values. It stays an exact fraction; the cent is settled by SalaryBase.
 */
final class IntegrationFactor
{
    private const int DAYS_IN_YEAR = 365;

    public static function for(int $aguinaldoDays, int $vacationDays, Rate $vacationPremium): Rate
    {
        return Rate::one()
            ->plus(Rate::fraction($aguinaldoDays, self::DAYS_IN_YEAR))
            ->plus(Rate::fraction($vacationDays, self::DAYS_IN_YEAR)->times($vacationPremium));
    }
}
