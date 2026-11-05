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
        return self::ofDays(Rate::scaled($aguinaldoDays, 0), Rate::scaled($vacationDays, 0), $vacationPremium);
    }

    /** The same, for days that are not whole (a contract may grant 22.5 days of aguinaldo). */
    public static function ofDays(Rate $aguinaldoDays, Rate $vacationDays, Rate $vacationPremium): Rate
    {
        $perDay = Rate::fraction(1, self::DAYS_IN_YEAR);

        return Rate::one()
            ->plus($aguinaldoDays->times($perDay))
            ->plus($vacationDays->times($perDay)->times($vacationPremium));
    }
}
