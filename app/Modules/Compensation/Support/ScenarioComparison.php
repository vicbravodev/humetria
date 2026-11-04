<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support;

use App\Modules\Compensation\Data\CostBreakdown;
use App\Modules\Compensation\Models\Scenario;
use App\Modules\Compensation\Support\Calculation\Money;
use App\Modules\Compensation\Support\Calculation\ScenarioCost;

/**
 * Two to four scenarios side by side: their totals and, for each concept of the cost, what each one changes in the first
 * year (the 12 months and the retroactive months). A concept a scenario does not have costs it nothing.
 */
final readonly class ScenarioComparison
{
    public function __construct(private ScenarioInputBuilder $inputs, private ConceptLabels $labels) {}

    /**
     * @param  list<Scenario>  $scenarios
     * @return array{scenarios: list<array<string, mixed>>, rows: list<array<string, mixed>>}
     */
    public function handle(array $scenarios): array
    {
        $costs = array_map(fn (Scenario $scenario): CostBreakdown => ScenarioCost::firstYear($this->inputs->build($scenario)), $scenarios);
        $names = $this->labels->benefitNames();

        // The concepts in the order of the cascade, the first scenario's first, then the ones only another has.
        $concepts = [];

        foreach ($costs as $cost) {
            foreach ($cost->concepts as $concept) {
                $concepts["{$concept->type->value}:{$concept->key}"] ??= $concept;
            }
        }

        usort($concepts, fn ($a, $b): int => $this->rank($a->type->value) <=> $this->rank($b->type->value));
        $rows = [];

        foreach ($concepts as $concept) {
            $rows[] = [
                'type' => $concept->type->value,
                'key' => $concept->key,
                'label' => $this->labels->label($concept->type, $concept->key, $names),
                'cells' => array_map(function (CostBreakdown $cost) use ($concept): string {
                    $own = array_find($cost->concepts, fn ($other): bool => $other->type === $concept->type && $other->key === $concept->key);

                    return Money::format($own?->firstYearCents() ?? 0);
                }, $costs),
            ];
        }

        return [
            'scenarios' => array_map(fn (Scenario $scenario, CostBreakdown $cost): array => [
                'id' => $scenario->id,
                'name' => $scenario->name,
                'people' => $cost->people,
                'first_year_text' => Money::format($cost->firstYearCents()),
                'recurring_text' => Money::format($cost->recurringCents),
            ], $scenarios, $costs),
            'rows' => $rows,
        ];
    }

    private function rank(string $type): int
    {
        return match ($type) {
            'salary' => 0,
            'benefit' => 1,
            default => 2,
        };
    }
}
