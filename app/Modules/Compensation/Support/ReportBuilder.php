<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support;

use App\Modules\Compensation\Data\Assumptions;
use App\Modules\Compensation\Data\ConceptCost;
use App\Modules\Compensation\Data\CostBreakdown;
use App\Modules\Compensation\Data\CostCut;
use App\Modules\Compensation\Data\GradeRaise;
use App\Modules\Compensation\Data\MonthlyCost;
use App\Modules\Compensation\Data\ProjectionYear;
use App\Modules\Compensation\Data\ScenarioInput;
use App\Modules\Compensation\Enums\AssumptionKey;
use App\Modules\Compensation\Enums\CostConcept;
use App\Modules\Compensation\Enums\PayGroup;
use App\Modules\Compensation\Models\Scenario;
use App\Modules\Compensation\Support\Calculation\Money;
use App\Modules\Compensation\Support\Calculation\Projection;
use App\Modules\Compensation\Support\Calculation\ScenarioCost;
use App\Modules\Compensation\Support\Calculation\SeniorityPremium;
use App\Modules\Compensation\Support\Calculation\Sensitivity;
use App\Modules\Organization\Contracts\Areas;
use App\Modules\Organization\Contracts\JobGrades;
use App\Modules\Results\Contracts\SmallGroups;
use Carbon\CarbonImmutable;

/**
 * Everything the internal report of a scenario shows, as the page takes it: money already written, names instead of ids, and
 * no person: the people who cannot be costed are named by the company's employee number, and nobody else is.
 */
final readonly class ReportBuilder
{
    /** The most employee numbers the page lists as missing; the rest are counted. */
    public const int MISSING_SHOWN = 100;

    public function __construct(
        private ScenarioInputBuilder $inputs,
        private FiscalParameters $fiscal,
        private ConceptLabels $labels,
        private Areas $areas,
        private JobGrades $jobGrades,
        private SmallGroups $smallGroups,
        private OtherScenarios $others,
    ) {}

    /**
     * The report for a page. For a link anyone can open (`$shared`) the groups under the platform's minimum are folded into
     * «Otros», and nobody is named: no employee number, no list of who was left out, no table of the worker's gross.
     *
     * @return array<string, mixed>
     */
    public function build(Scenario $scenario, bool $shared = false): array
    {
        $input = $this->inputs->build($scenario);
        $cost = ScenarioCost::firstYear($input);
        $cuts = $shared ? SharedGroups::apply($cost, $this->smallGroups) : $cost;
        $names = $this->labels->benefitNames();
        $assumptions = $this->assumptions($scenario);
        $years = Projection::years($input, $assumptions['values'], $scenario->horizon_years, fn (CarbonImmutable $on) => $this->fiscal->at($on));

        return [
            'scenario' => [
                'id' => $scenario->id, 'name' => $scenario->name, 'effective_on' => $scenario->effective_on->toDateString(),
                'horizon_years' => $scenario->horizon_years, 'retroactive_months' => $scenario->retroactive_months,
                'unionized_raise' => PercentBasisPoints::format($scenario->group_changes['unionized'] ?? 0),
                'trusted_raise' => ($scenario->group_changes['trusted'] ?? null) === null ? null : PercentBasisPoints::format($scenario->group_changes['trusted']),
                'raise_text' => RaiseText::of($scenario), 'raise_unit' => $scenario->raise_unit->value,
            ],
            'coverage' => $this->coverage($cost, $shared),
            'headline' => [
                'people' => $cost->people, 'retroactive_months' => $scenario->retroactive_months,
                'first_year_text' => Money::format($cost->firstYearCents()), 'recurring_text' => Money::format($cost->recurringCents),
                'retroactive_text' => Money::format($cost->retroactiveCents),
                'before_text' => Money::format($cost->beforeCents), 'after_text' => Money::format($cost->afterCents),
                'first_year_cents' => $cost->firstYearCents(), 'recurring_cents' => $cost->recurringCents, 'retroactive_cents' => $cost->retroactiveCents,
                'before_cents' => $cost->beforeCents, 'after_cents' => $cost->afterCents,
            ],
            'concepts' => array_map(fn (ConceptCost $concept): array => $this->concept($concept, $names), array_values(array_filter($cost->concepts, fn (ConceptCost $concept): bool => $concept->beforeCents !== 0 || $concept->afterCents !== 0 || $concept->retroactiveCents !== 0))),
            'months' => array_map(fn (MonthlyCost $month): array => [
                'month' => $month->month->toDateString(), 'before_text' => Money::format($month->beforeCents),
                'after_text' => Money::format($month->afterCents), 'delta_text' => Money::format($month->deltaCents()),
            ], $cost->months),
            'cuts' => $this->cuts($cuts),
            'raises' => $this->raises(ScenarioCost::monthlyRaises($input), $shared),
            'others' => $shared ? [] : $this->others->except($scenario),
            'projection' => array_map(fn (ProjectionYear $year): array => $this->year($year), $years),
            'seniority' => $this->seniority($input),
            'sensitivity' => $this->sensitivity($input),
            'assumptions' => $assumptions['rows'],
            'warnings' => $this->warnings($cost, $input, $years, $shared),
            'person_value' => $shared ? ['rows' => [], 'person_total_text' => Money::format(0), 'company_total_text' => Money::format(0)] : $this->personValue($cost, $input, $names),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function coverage(CostBreakdown $cost, bool $shared): array
    {
        $gaps = $cost->gaps;

        return [
            'total' => $gaps->total, 'costed' => $gaps->costed,
            'missing' => $shared ? [] : array_slice($gaps->missing, 0, self::MISSING_SHOWN),
            'missing_more' => $shared ? 0 : max(0, count($gaps->missing) - self::MISSING_SHOWN),
            'missing_without_number' => $shared ? 0 : $gaps->missingWithoutNumber,
            'causes' => ['without_grade' => $gaps->withoutGrade, 'without_hire_date' => $gaps->withoutHireDate, 'without_group' => $gaps->withoutGroup],
        ];
    }

    /**
     * @param  array<string, string>  $names
     * @return array<string, mixed>
     */
    private function concept(ConceptCost $concept, array $names): array
    {
        return [
            'type' => $concept->type->value, 'key' => $concept->key, 'label' => $this->labels->label($concept->type, $concept->key, $names),
            'before_text' => Money::format($concept->beforeCents), 'after_text' => Money::format($concept->afterCents),
            'delta_text' => Money::format($concept->deltaCents()), 'first_year_text' => Money::format($concept->firstYearCents()),
            'first_year_cents' => $concept->firstYearCents(),
        ];
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function cuts(CostBreakdown $cost): array
    {
        $areaNames = $this->areas->names($this->ids($cost->byArea));
        $gradeNames = $this->jobGrades->findMany($this->ids($cost->byGrade));

        return [
            'areas' => $this->cutRows($cost->byArea, fn (?string $key): string => $key === null ? 'Sin área' : ($key === SharedGroups::OTHERS ? 'Otros' : ($areaNames[$key] ?? 'Área'))),
            'grades' => $this->cutRows($cost->byGrade, fn (?string $key): string => $key === SharedGroups::OTHERS ? 'Otros' : ($key === null || ! isset($gradeNames[$key]) ? 'Categoría' : "{$gradeNames[$key]->category} · {$gradeNames[$key]->level}")),
            'groups' => $this->cutRows($cost->byGroup, fn (?string $key): string => $key === SharedGroups::OTHERS ? 'Otros' : (PayGroup::tryFrom((string) $key)?->label() ?? 'Grupo')),
        ];
    }

    /**
     * The ids a cut names, without the folded group, which is no one's id.
     *
     * @param  list<CostCut>  $cuts
     * @return list<string>
     */
    private function ids(array $cuts): array
    {
        return array_values(array_filter(array_map(fn (CostCut $cut): ?string => $cut->key, $cuts), fn (?string $key): bool => $key !== null && $key !== SharedGroups::OTHERS));
    }

    /**
     * @param  list<CostCut>  $cuts
     * @param  callable(?string): string  $label
     * @return list<array<string, mixed>>
     */
    private function cutRows(array $cuts, callable $label): array
    {
        $rows = array_map(fn (CostCut $cut): array => [
            'label' => $label($cut->key), 'people' => $cut->people, 'before_text' => Money::format($cut->beforeCents),
            'after_text' => Money::format($cut->afterCents), 'delta_text' => Money::format($cut->deltaCents()),
        ], $cuts);
        usort($rows, fn (array $a, array $b): int => $b['people'] <=> $a['people'] ?: strcmp($a['label'], $b['label']));

        return $rows;
    }

    /**
     * What each person of a category and level receives more a month, by group: the sindicalizados first, the most first. For a
     * link anyone can open, the categories under the platform's minimum of people stay out, with the complementary ones.
     *
     * @param  list<GradeRaise>  $raises
     * @return list<array{label: string, group: string, people: int, monthly_raise_cents: int}>
     */
    private function raises(array $raises, bool $shared): array
    {
        $headcount = [];

        foreach ($raises as $raise) {
            $headcount["{$raise->jobGradeId}|{$raise->group->value}"] = $raise->people;
        }

        $visible = $shared ? array_flip($this->smallGroups->visibleKeys($headcount)) : $headcount;
        $raises = array_values(array_filter($raises, fn (GradeRaise $raise): bool => isset($visible["{$raise->jobGradeId}|{$raise->group->value}"])));
        $names = $this->jobGrades->findMany(array_values(array_unique(array_map(fn (GradeRaise $raise): string => $raise->jobGradeId, $raises))));
        $rows = array_map(fn (GradeRaise $raise): array => [
            'label' => isset($names[$raise->jobGradeId]) ? "{$names[$raise->jobGradeId]->category} · {$names[$raise->jobGradeId]->level}" : 'Categoría',
            'group' => $raise->group->value, 'people' => $raise->people, 'monthly_raise_cents' => $raise->monthlyRaiseCents,
        ], $raises);
        usort($rows, fn (array $a, array $b): int => [$a['group'] !== PayGroup::Unionized->value, $b['monthly_raise_cents'], $a['label']] <=> [$b['group'] !== PayGroup::Unionized->value, $a['monthly_raise_cents'], $b['label']]);

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function year(ProjectionYear $year): array
    {
        return [
            'year' => $year->year, 'effective_on' => $year->effectiveOn->toDateString(), 'people' => $year->people,
            'before_text' => Money::format($year->beforeCents), 'after_text' => Money::format($year->afterCents),
            'delta_text' => Money::format($year->deltaCents()),
            'retroactive_text' => $year->retroactiveCents === 0 ? null : Money::format($year->retroactiveCents),
            'cost_cents' => $year->costCents(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function seniority(ScenarioInput $input): array
    {
        $estimate = SeniorityPremium::estimate($input);

        return [
            'people' => $estimate->people, 'before_text' => Money::format($estimate->beforeCents),
            'after_text' => Money::format($estimate->afterCents), 'delta_text' => Money::format($estimate->deltaCents()),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function sensitivity(ScenarioInput $input): array
    {
        $result = Sensitivity::around($input);

        return [
            'one_point_text' => Money::format($result->onePointCents), 'base_text' => Money::format($result->baseCents),
            'minus_text' => Money::format($result->minusOneCents), 'plus_text' => Money::format($result->plusOneCents),
        ];
    }

    /**
     * The assumptions for the projection and as the report lists them.
     *
     * @return array{values: Assumptions, rows: list<array<string, string>>}
     */
    private function assumptions(Scenario $scenario): array
    {
        $snapshot = $scenario->assumptions + AssumptionKey::defaultSnapshot();
        $rows = [];

        foreach (AssumptionKey::cases() as $key) {
            $rows[] = ['label' => $key->label(), 'value' => PercentBasisPoints::format($snapshot[$key->value]['value_bp']), 'source' => $snapshot[$key->value]['source'], 'set_on' => $snapshot[$key->value]['set_on']];
        }

        $bp = fn (AssumptionKey $key): int => $snapshot[$key->value]['value_bp'];

        return [
            'values' => new Assumptions($bp(AssumptionKey::Inflation), $bp(AssumptionKey::Turnover), $bp(AssumptionKey::HeadcountGrowth), $bp(AssumptionKey::FutureRevisions), $bp(AssumptionKey::FollowingYearsRate)),
            'rows' => $rows,
        ];
    }

    /**
     * What a reader must know before trusting the numbers.
     *
     * @param  list<ProjectionYear>  $years
     * @return array<string, mixed>
     */
    private function warnings(CostBreakdown $cost, ScenarioInput $input, array $years, bool $shared): array
    {
        $individuals = count($input->individuals);
        $gradeNames = $this->jobGrades->findMany(array_keys($cost->uncostedGrades));
        $stale = array_values(array_unique([...$cost->staleKeys, ...array_merge([], ...array_map(fn (ProjectionYear $year): array => $year->staleKeys, $years))]));
        $stale = array_values(array_unique(array_map($this->labels->fiscalLabel(...), $stale)));
        sort($stale);

        return [
            'individual' => [
                'count' => $shared ? 0 : $individuals,
                'percent' => $cost->people === 0 ? '0' : $this->tenths($individuals, $cost->people),
                'over_tenth' => $individuals * 10 > $cost->people,
            ],
            'uncosted' => $shared ? [] : array_map(
                fn (string $id, int $people): array => ['label' => isset($gradeNames[$id]) ? "{$gradeNames[$id]->category} · {$gradeNames[$id]->level}" : 'Categoría', 'people' => $people],
                array_keys($cost->uncostedGrades),
                array_values($cost->uncostedGrades),
            ),
            'uncalculated' => array_map(fn (string $line): string => $this->labels->label(CostConcept::Contribution, $line, []), $cost->uncalculatedLines),
            'stale' => $stale,
        ];
    }

    /** The share of `$part` in `$whole` as a percent with one decimal and no trailing zero: 1 of 9 is "11.1", 1 of 10 is "10". */
    private function tenths(int $part, int $whole): string
    {
        $tenths = intdiv($part * 1_000 + intdiv($whole, 2), $whole);

        return intdiv($tenths, 10).($tenths % 10 === 0 ? '' : '.'.($tenths % 10));
    }

    /**
     * The worker's gross against the company's cost: the salary and each benefit as the person receives them, and what the
     * employer lines add on top. Whether a benefit is exempt from the worker's income tax is only pointed out.
     *
     * @param  array<string, string>  $names
     * @return array<string, mixed>
     */
    private function personValue(CostBreakdown $cost, ScenarioInput $input, array $names): array
    {
        $exempt = [];

        foreach ([...$input->benefits, ...$input->proposed] as $spec) {
            $exempt[$spec->key] = ($exempt[$spec->key] ?? false) || $spec->incomeTaxExempt;
        }

        $rows = [];
        $person = 0;
        $company = 0;

        foreach ($cost->concepts as $concept) {
            $isPerson = $concept->type !== CostConcept::Contribution;
            $person += $isPerson ? $concept->firstYearCents() : 0;
            $company += $concept->firstYearCents();
            $rows[] = [
                'label' => $this->labels->label($concept->type, $concept->key, $names), 'type' => $concept->type->value,
                'person_text' => $isPerson ? Money::format($concept->firstYearCents()) : null,
                'company_text' => Money::format($concept->firstYearCents()),
                'exempt' => $concept->type === CostConcept::Benefit && ($exempt[$concept->key] ?? false),
            ];
        }

        return ['rows' => $rows, 'person_total_text' => Money::format($person), 'company_total_text' => Money::format($company)];
    }
}
