<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support;

use App\Modules\Compensation\Data\ConceptCost;
use App\Modules\Compensation\Data\CostBreakdown;
use App\Modules\Compensation\Data\ScenarioInput;
use App\Modules\Compensation\Enums\CostConcept;
use App\Modules\Compensation\Enums\PayGroup;
use App\Modules\Compensation\Enums\RaiseUnit;
use App\Modules\Compensation\Models\Scenario;
use App\Modules\Compensation\Support\Calculation\Money;
use App\Modules\Compensation\Support\Calculation\ScenarioCost;
use App\Modules\Compensation\Support\Calculation\Sensitivity;

/**
 * Two to four scenarios side by side (board V4-Comp-Comparar), from the cheapest: what each costs in its first year and each
 * year after, and for each concept what each changes in the first year (the 12 months and the retroactive months). The
 * distance to negotiate is between the two dearest, with what a point and a month of retroactivity cost on the dearest. A
 * concept a scenario does not have costs it nothing, and one that no scenario changes is left out.
 */
final readonly class ScenarioComparison
{
    public function __construct(private ScenarioInputBuilder $inputs, private ConceptLabels $labels) {}

    /**
     * @param  list<Scenario>  $scenarios
     * @return array<string, mixed>
     */
    public function handle(array $scenarios): array
    {
        $entries = array_map(function (Scenario $scenario): array {
            $input = $this->inputs->build($scenario);

            return ['scenario' => $scenario, 'input' => $input, 'cost' => ScenarioCost::firstYear($input)];
        }, $scenarios);
        usort($entries, fn (array $a, array $b): int => $a['cost']->firstYearCents() <=> $b['cost']->firstYearCents());
        $costs = array_column($entries, 'cost');
        $lowest = $costs[0]->firstYearCents();
        $last = count($entries) - 1;

        return [
            'scenarios' => array_map(fn (array $entry): array => [
                'id' => $entry['scenario']->id,
                'name' => $entry['scenario']->name,
                'raise_text' => RaiseText::of($entry['scenario']),
                'retroactive_months' => $entry['scenario']->retroactive_months,
                'people' => $entry['cost']->people,
                'first_year_text' => Money::pesos($entry['cost']->firstYearCents()),
                'recurring_text' => Money::pesos($entry['cost']->recurringCents),
                'above_lowest_text' => $entry['cost']->firstYearCents() === $lowest ? null : self::signed($entry['cost']->firstYearCents() - $lowest),
            ], $entries),
            'rows' => $this->rows($costs),
            'totals' => [
                'first_year' => array_map(fn (CostBreakdown $cost): string => Money::pesos($cost->firstYearCents()), $costs),
                'recurring' => array_map(fn (CostBreakdown $cost): string => Money::pesos($cost->recurringCents), $costs),
                'first_year_difference' => self::signed($costs[$last]->firstYearCents() - $costs[$last - 1]->firstYearCents()),
                'recurring_difference' => self::signed($costs[$last]->recurringCents - $costs[$last - 1]->recurringCents),
            ],
            'distance' => $this->distance($entries[$last - 1], $entries[$last]),
            'moves' => self::moves($entries[$last]['input'], $costs[$last]),
            'midpoint' => self::midpoint($entries[$last - 1]['scenario'], $entries[$last]['scenario']),
        ];
    }

    /**
     * @param  list<CostBreakdown>  $costs  from the cheapest
     * @return list<array<string, mixed>>
     */
    private function rows(array $costs): array
    {
        $names = $this->labels->benefitNames();
        $concepts = [];

        // The concepts in the order of the cascade, the first scenario's first, then the ones only another has.
        foreach ($costs as $cost) {
            foreach ($cost->concepts as $concept) {
                $concepts["{$concept->type->value}:{$concept->key}"] ??= $concept;
            }
        }

        usort($concepts, fn (ConceptCost $a, ConceptCost $b): int => self::rank($a->type) <=> self::rank($b->type));
        $rows = [];

        foreach ($concepts as $concept) {
            $cents = array_map(fn (CostBreakdown $cost): int => self::of($cost, $concept)?->firstYearCents() ?? 0, $costs);

            // A concept no scenario touches costs nothing in any of them: it says nothing about the choice.
            if (array_filter($cents) === []) {
                continue;
            }

            $rows[] = [
                'type' => $concept->type->value,
                'key' => $concept->key,
                'label' => $this->labels->label($concept->type, $concept->key, $names),
                'cells' => array_map(Money::pesos(...), $cents),
                'difference_text' => self::signed($cents[count($cents) - 1] - $cents[count($cents) - 2]),
            ];
        }

        return $rows;
    }

    /**
     * Between the two dearest: how much apart they are and which concept makes most of it.
     *
     * @param  array{scenario: Scenario, input: ScenarioInput, cost: CostBreakdown}  $lower
     * @param  array{scenario: Scenario, input: ScenarioInput, cost: CostBreakdown}  $higher
     * @return array{from: string, to: string, first_year_text: string, recurring_text: string, mostly: ?string}
     */
    private function distance(array $lower, array $higher): array
    {
        $largest = null;
        $largestCents = 0;

        foreach ($higher['cost']->concepts as $concept) {
            $apart = $concept->firstYearCents() - (self::of($lower['cost'], $concept)?->firstYearCents() ?? 0);

            if ($apart > $largestCents) {
                [$largest, $largestCents] = [$concept, $apart];
            }
        }

        return [
            'from' => $lower['scenario']->name,
            'to' => $higher['scenario']->name,
            'first_year_text' => Money::pesos(abs($higher['cost']->firstYearCents() - $lower['cost']->firstYearCents())),
            'recurring_text' => Money::pesos(abs($higher['cost']->recurringCents - $lower['cost']->recurringCents)),
            'mostly' => $largest === null ? null : mb_strtolower($this->labels->label($largest->type, $largest->key, $this->labels->benefitNames())),
        ];
    }

    /**
     * «Cómo se mueve»: about what a point to each group costs a year and a month of retroactivity, on the dearest.
     *
     * @return array{unionized_point_text: string, trusted_point_text: ?string, retroactive_month_text: string}
     */
    private static function moves(ScenarioInput $input, CostBreakdown $cost): array
    {
        $trusted = Sensitivity::pointFor($input, PayGroup::Trusted);

        return [
            'unionized_point_text' => Money::about(Sensitivity::pointFor($input, PayGroup::Unionized)),
            'trusted_point_text' => $trusted === 0 ? null : Money::about($trusted),
            'retroactive_month_text' => Money::about($cost->retroactiveMonthCents()),
        ];
    }

    /**
     * The point in between two scenarios that raise the same way, as the editor types it; none when one is a percent and the
     * other an amount.
     *
     * @return ?array{raise_unit: string, unionized_raise: string, trusted_raise: string, retroactive_months: int}
     */
    private static function midpoint(Scenario $lower, Scenario $higher): ?array
    {
        if ($lower->raise_unit !== $higher->raise_unit) {
            return null;
        }

        $half = fn (?int $a, ?int $b): int => intdiv(($a ?? 0) + ($b ?? 0) + 1, 2);
        $trusted = ($lower->group_changes['trusted'] ?? null) === null && ($higher->group_changes['trusted'] ?? null) === null
            ? null
            : $half($lower->group_changes['trusted'] ?? $lower->group_changes['unionized'] ?? 0, $higher->group_changes['trusted'] ?? $higher->group_changes['unionized'] ?? 0);
        $text = fn (int $value): string => $lower->raise_unit === RaiseUnit::Amount ? BenefitValue::decimal($value) : PercentBasisPoints::format($value);

        return [
            'raise_unit' => $lower->raise_unit->value,
            'unionized_raise' => $text($half($lower->group_changes['unionized'] ?? 0, $higher->group_changes['unionized'] ?? 0)),
            'trusted_raise' => $trusted === null ? '' : $text($trusted),
            'retroactive_months' => intdiv($lower->retroactive_months + $higher->retroactive_months + 1, 2),
        ];
    }

    private static function of(CostBreakdown $cost, ConceptCost $concept): ?ConceptCost
    {
        return array_find($cost->concepts, fn (ConceptCost $other): bool => $other->type === $concept->type && $other->key === $concept->key);
    }

    /** "+$1,825", "-$1,825" or "$0". */
    private static function signed(int $cents): string
    {
        return $cents > 0 ? '+'.Money::pesos($cents) : Money::pesos($cents);
    }

    private static function rank(CostConcept $type): int
    {
        return match ($type) {
            CostConcept::Salary => 0,
            CostConcept::Benefit => 1,
            CostConcept::Contribution => 2,
        };
    }
}
