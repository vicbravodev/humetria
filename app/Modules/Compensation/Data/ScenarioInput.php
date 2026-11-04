<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Data;

use App\Modules\Compensation\Support\Calculation\Rate;
use App\Modules\Organization\Data\HeadcountCell;
use App\Modules\Organization\Data\RosterGaps;
use Carbon\CarbonImmutable;

/**
 * Everything the first-year cost of a scenario depends on, already read: no model, no query.
 *
 * `raises` are fractions of the salary by staff group ("unionized", "trusted"); the trusted copy the unionized when they
 * have none of their own. `proposed` benefits replace the current one with the same key, or are new.
 */
final readonly class ScenarioInput
{
    /**
     * @param  list<HeadcountCell>  $cells  people grouped by category and level, area, group, state and month of hire; those with an individual salary are not in them
     * @param  list<IndividualSalary>  $individuals
     * @param  array<string, int>  $gradeSalaries  daily salary in cents by category and level id, as of the effective date
     * @param  list<BenefitSpec>  $benefits  the current contract
     * @param  array<string, Rate>  $raises
     * @param  list<BenefitSpec>  $proposed
     * @param  ?Rate  $workRiskPremium  the account's premium; null leaves the work risk line uncalculated
     * @param  ?string  $defaultState  where people with no state are taken to work; null leaves their payroll tax uncalculated
     */
    public function __construct(
        public CarbonImmutable $effectiveOn,
        public int $retroMonths,
        public FiscalSnapshot $fiscal,
        public array $cells,
        public array $individuals,
        public array $gradeSalaries,
        public array $benefits,
        public array $raises,
        public array $proposed,
        public ?Rate $workRiskPremium,
        public ?string $defaultState,
        public RosterGaps $gaps,
    ) {}
}
