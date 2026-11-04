<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Data;

use App\Modules\Compensation\Support\Calculation\IntegrationFactor;
use App\Modules\Compensation\Support\Calculation\Rate;

/**
 * The aguinaldo, the vacation and the vacation premium one person really has on one day (the contract's, or the law's
 * when the contract says nothing for them). These three form the integration factor of the SBC.
 */
final readonly class LawTerms
{
    public function __construct(
        public Rate $aguinaldoDays,
        public Rate $vacationDays,
        public Rate $vacationPremium,
        public bool $aguinaldoTaxable,
        public bool $premiumTaxable,
    ) {}

    public function factor(): Rate
    {
        return IntegrationFactor::ofDays($this->aguinaldoDays, $this->vacationDays, $this->vacationPremium);
    }

    /** The aguinaldo of a year, in cents. */
    public function aguinaldoCost(int $dailySalaryCents): int
    {
        return $this->aguinaldoDays->times(Rate::scaled($dailySalaryCents, 0))->of(1);
    }

    /** The premium of a year: the premium percent of the salary of the vacation days, in cents. */
    public function premiumCost(int $dailySalaryCents): int
    {
        return $this->vacationDays->times($this->vacationPremium)->times(Rate::scaled($dailySalaryCents, 0))->of(1);
    }
}
