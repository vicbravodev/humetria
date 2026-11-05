<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support;

use App\Modules\Compensation\Actions\SaveScenario;
use App\Modules\Compensation\Data\ConceptCost;
use App\Modules\Compensation\Data\CostBreakdown;
use App\Modules\Compensation\Data\ScenarioData;
use App\Modules\Compensation\Enums\CostConcept;
use App\Modules\Compensation\Enums\PayGroup;
use App\Modules\Compensation\Models\Benefit;
use App\Modules\Compensation\Models\Scenario;
use App\Modules\Compensation\Support\Calculation\Money;
use App\Modules\Compensation\Support\Calculation\Rate;
use App\Modules\Compensation\Support\Calculation\ScenarioCost;
use Illuminate\Validation\ValidationException;

/**
 * The live cost of a scenario that is being typed (decision A12): the same engine as the report, on a scenario that is never
 * saved, so the editor shows the numbers the report will show. Only the headline: no projection, no cuts.
 */
final readonly class ScenarioPreview
{
    public function __construct(private SaveScenario $save, private ScenarioInputBuilder $inputs) {}

    /**
     * @return array<string, mixed>
     *
     * @throws ValidationException what makes the scenario impossible to cost, by field; a missing name does not
     */
    public function handle(ScenarioData $data): array
    {
        $problems = $this->save->problems($data);
        unset($problems['name']);

        if ($problems !== []) {
            throw ValidationException::withMessages($problems);
        }

        $cost = ScenarioCost::firstYear($this->inputs->build(new Scenario($this->save->attributes($data))));
        $gaps = $cost->gaps;

        return [
            'people' => $cost->people,
            'groups' => $this->groups($cost),
            'left_out' => $gaps->total - $gaps->costed + array_sum($cost->uncostedGrades),
            'first_year_cents' => $cost->firstYearCents(),
            'first_year_text' => Money::pesos($cost->firstYearCents()),
            'retroactive_text' => Money::pesos($cost->retroactiveCents),
            'retroactive_month_text' => Money::pesos($cost->retroactiveMonthCents()),
            'recurring_text' => Money::pesos($cost->recurringCents),
            'per_person_month_text' => Money::pesos($cost->people === 0 ? 0 : Rate::fraction($cost->recurringCents, 12 * $cost->people)->of(1)),
            'over_today_text' => self::share($cost->firstYearCents(), $cost->beforeCents),
            'benefits' => $this->benefits($cost, $data),
        ];
    }

    /** @return array<string, int> the people costed in each group, both always there */
    private function groups(CostBreakdown $cost): array
    {
        $people = [];

        foreach (PayGroup::cases() as $group) {
            $people[$group->value] = array_find($cost->byGroup, fn ($cut): bool => $cut->key === $group->value)->people ?? 0;
        }

        return $people;
    }

    /** A part of a whole as a percent with one decimal and no trailing zero: 1 of 16 is "6.3", 1 of 10 is "10"; of nothing, "0". */
    public static function share(int $part, int $whole): string
    {
        if ($whole === 0) {
            return '0';
        }

        $tenths = intdiv(abs($part) * 1_000 + intdiv($whole, 2), $whole);

        return ($part < 0 ? '-' : '').intdiv($tenths, 10).($tenths % 10 === 0 ? '' : '.'.($tenths % 10));
    }

    /**
     * What each benefit that changes adds a year, by the benefit's id (the calculation knows it by its key).
     *
     * @return list<array{benefit_id: string, yearly_text: string}>
     */
    private function benefits(CostBreakdown $cost, ScenarioData $data): array
    {
        $ids = array_map(fn ($change): string => $change->benefitId, $data->termChanges);
        $rows = [];

        foreach (Benefit::query()->whereIn('id', $ids)->get(['id', 'base_key']) as $benefit) {
            $concept = array_find($cost->concepts, fn (ConceptCost $concept): bool => $concept->type === CostConcept::Benefit && $concept->key === ($benefit->base_key ?? $benefit->id));
            $rows[] = ['benefit_id' => $benefit->id, 'yearly_text' => Money::pesos($concept === null ? 0 : $concept->recurringCents)];
        }

        return $rows;
    }
}
