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
 * `raises` are fractions of the salary by staff group ("unionized", "trusted") and `monthlyAmounts` fixed amounts a month in
 * cents added to the salary before the raise; the trusted copy the unionized when they have neither of their own. `proposed` benefits replace the current one with the same key, or are new.
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
     * @param  array<string, int>  $monthlyAmounts
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
        public array $monthlyAmounts = [],
    ) {}

    /**
     * The same scenario with other changes proposed; the roster, the contract and the fiscal values are the same.
     *
     * @param  array<string, Rate>  $raises
     * @param  list<BenefitSpec>  $proposed
     * @param  array<string, int>  $monthlyAmounts
     */
    public function withChanges(array $raises, array $proposed, array $monthlyAmounts = []): self
    {
        return new self(
            $this->effectiveOn, $this->retroMonths, $this->fiscal, $this->cells, $this->individuals, $this->gradeSalaries,
            $this->benefits, $raises, $proposed, $this->workRiskPremium, $this->defaultState, $this->gaps, $monthlyAmounts,
        );
    }
}
