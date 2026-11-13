<?php

declare(strict_types=1);

namespace App\Modules\Leave\Contracts;

use App\Modules\Leave\Data\CompanyVacationTerms;

/**
 * What the vacations policy of the current company gives, for the modules that put it next to what the contract says
 * (Compensación): the days of the first year and the premium of the policy that applies by default.
 */
interface VacationTerms
{
    /**
     * @return CompanyVacationTerms|null null when the company does not use Vacaciones
     */
    public function company(): ?CompanyVacationTerms;
}
