<?php

declare(strict_types=1);

namespace App\Modules\Leave\Data;

/** The default vacations policy of a company: the days of the first year of service (both tranches) and the premium, as a percentage. */
final readonly class CompanyVacationTerms
{
    public function __construct(public int $firstYearDays, public int $premiumPercent) {}
}
