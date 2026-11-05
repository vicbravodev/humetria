<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support;

use App\Modules\Compensation\Data\BenefitSpec;
use App\Modules\Compensation\Data\IndividualSalary;
use App\Modules\Compensation\Data\ScenarioInput;
use App\Modules\Compensation\Enums\PayGroup;
use App\Modules\Compensation\Enums\RaiseUnit;
use App\Modules\Compensation\Models\Benefit;
use App\Modules\Compensation\Models\ContractTerm;
use App\Modules\Compensation\Models\GradeSalary;
use App\Modules\Compensation\Models\SalaryOverride;
use App\Modules\Compensation\Models\Scenario;
use App\Modules\Compensation\Models\WorkRiskPremium;
use App\Modules\Compensation\Support\Calculation\Rate;
use App\Modules\Organization\Contracts\CompensationRoster;
use Carbon\CarbonImmutable;

/**
 * Everything the cost of a scenario depends on, read from the current company and the platform on the scenario's effective
 * date: the roster counted by cell (never the people), the tabulator salaries and individual salaries in force, the benefits
 * with their terms, the work risk premium and the fiscal values. The calculation itself does no reading.
 */
final readonly class ScenarioInputBuilder
{
    public function __construct(private CompensationRoster $roster, private FiscalParameters $fiscal) {}

    public function build(Scenario $scenario): ScenarioInput
    {
        $on = $scenario->effective_on;
        $overrides = $this->overridesInForce($on);
        $individuals = [];

        foreach ($this->roster->individuals(array_column($overrides, 'number')) as $number => $person) {
            $individuals[] = new IndividualSalary($person, $overrides[mb_strtolower($number)]['cents']);
        }

        $benefits = [];

        foreach (Benefit::query()->whereNull('archived_at')->orderBy('created_at')->orderBy('id')->get() as $benefit) {
            $benefits[$benefit->id] = $benefit;
        }

        return new ScenarioInput(
            effectiveOn: $on,
            retroMonths: $scenario->retroactive_months,
            fiscal: $this->fiscal->at($on),
            cells: $this->roster->cells($on, array_column($overrides, 'number')),
            individuals: $individuals,
            gradeSalaries: $this->gradeSalaries($on),
            benefits: $this->currentSpecs($benefits, $on),
            raises: $scenario->raise_unit === RaiseUnit::Percent ? $this->raises($scenario) : [],
            proposed: $this->proposed($scenario, $benefits),
            workRiskPremium: $this->riskPremium($on),
            defaultState: $scenario->default_state,
            gaps: $this->roster->gaps(),
            monthlyAmounts: $scenario->raise_unit === RaiseUnit::Amount ? $this->amounts($scenario) : [],
        );
    }

    /**
     * The individual salary in force on the date of each person who has one, by lower-case employee number.
     *
     * @return array<string, array{number: string, cents: int}>
     */
    private function overridesInForce(CarbonImmutable $on): array
    {
        $overrides = [];

        foreach (SalaryOverride::query()->where('effective_from', '<=', $on->toDateString())->orderBy('effective_from')->get(['employee_number', 'daily_salary_cents']) as $override) {
            $overrides[mb_strtolower($override->employee_number)] = ['number' => $override->employee_number, 'cents' => $override->daily_salary_cents];
        }

        return $overrides;
    }

    /** @return array<string, int> the daily salary in force on the date, by category and level id */
    private function gradeSalaries(CarbonImmutable $on): array
    {
        $salaries = [];

        $rows = GradeSalary::query()
            ->selectRaw('distinct on (job_grade_id) job_grade_id, daily_salary_cents')
            ->where('effective_from', '<=', $on->toDateString())
            ->orderBy('job_grade_id')
            ->orderByDesc('effective_from')
            ->toBase()
            ->get();

        foreach ($rows as $row) {
            if (is_string($row->job_grade_id) && is_int($row->daily_salary_cents)) {
                $salaries[$row->job_grade_id] = $row->daily_salary_cents;
            }
        }

        return $salaries;
    }

    /**
     * The contract in force: each active benefit with the latest term that started on or before the date; one with no term
     * yet is not in the contract.
     *
     * @param  array<string, Benefit>  $benefits
     * @return list<BenefitSpec>
     */
    private function currentSpecs(array $benefits, CarbonImmutable $on): array
    {
        $terms = [];

        foreach (ContractTerm::query()->whereIn('benefit_id', array_keys($benefits))->where('effective_from', '<=', $on->toDateString())->orderBy('effective_from')->get() as $term) {
            $terms[$term->benefit_id] = $term;
        }

        $specs = [];

        foreach ($benefits as $id => $benefit) {
            if (isset($terms[$id])) {
                $specs[] = BenefitSpecs::from($benefit, $terms[$id]->value, $terms[$id]->tenure_table);
            }
        }

        return $specs;
    }

    /**
     * @param  array<string, Benefit>  $benefits  the active ones
     * @return list<BenefitSpec>
     */
    private function proposed(Scenario $scenario, array $benefits): array
    {
        $proposed = [];

        foreach ($scenario->term_changes as $change) {
            if (isset($benefits[$change['benefit_id']])) {
                $proposed[] = BenefitSpecs::from($benefits[$change['benefit_id']], $change['value'], $change['tenure_table']);
            }
        }

        return $proposed;
    }

    /** @return array<string, Rate> */
    private function raises(Scenario $scenario): array
    {
        $raises = [];

        foreach (PayGroup::cases() as $group) {
            $basisPoints = $scenario->group_changes[$group->value] ?? null;

            if ($basisPoints !== null) {
                $raises[$group->value] = Rate::scaled($basisPoints, 4);
            }
        }

        return $raises;
    }

    /** @return array<string, int> the fixed amount a month of each group that has one, in cents */
    private function amounts(Scenario $scenario): array
    {
        $amounts = [];

        foreach (PayGroup::cases() as $group) {
            $cents = $scenario->group_changes[$group->value] ?? null;

            if ($cents !== null) {
                $amounts[$group->value] = $cents;
            }
        }

        return $amounts;
    }

    private function riskPremium(CarbonImmutable $on): ?Rate
    {
        $premium = WorkRiskPremium::query()->where('effective_from', '<=', $on->toDateString())->orderByDesc('effective_from')->first();

        return $premium === null ? null : Rate::scaled($premium->rate_scaled, WorkRiskPremium::SCALE);
    }
}
