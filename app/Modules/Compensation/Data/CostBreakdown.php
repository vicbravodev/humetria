<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Data;

use App\Modules\Organization\Data\RosterGaps;

/**
 * What a scenario costs in its first year, in cents. The 12 months, the concepts and every cut add up to exactly
 * `beforeCents` and `afterCents`; `retroactiveCents` is the months of retroactivity at the first month's change.
 */
final readonly class CostBreakdown
{
    /**
     * @param  int  $recurringCents  what the change costs per year at the effective date
     * @param  int  $people  people costed
     * @param  list<ConceptCost>  $concepts
     * @param  list<MonthlyCost>  $months  12
     * @param  list<CostCut>  $byArea
     * @param  list<CostCut>  $byGrade
     * @param  list<CostCut>  $byGroup
     * @param  array<string, int>  $uncostedGrades  people left out for want of a tabulator salary, by category and level id
     * @param  list<string>  $uncalculatedLines  contribution lines that could not be calculated (work_risk, payroll_tax)
     * @param  list<string>  $staleKeys  fiscal values carried past their vigencia
     */
    public function __construct(
        public int $beforeCents,
        public int $afterCents,
        public int $recurringCents,
        public int $retroactiveCents,
        public int $people,
        public array $concepts,
        public array $months,
        public array $byArea,
        public array $byGrade,
        public array $byGroup,
        public array $uncostedGrades,
        public array $uncalculatedLines,
        public array $staleKeys,
        public RosterGaps $gaps,
    ) {}

    public function deltaCents(): int
    {
        return $this->afterCents - $this->beforeCents;
    }

    /** The change over the 12 months plus the retroactive months. */
    public function firstYearCents(): int
    {
        return $this->deltaCents() + $this->retroactiveCents;
    }
}
