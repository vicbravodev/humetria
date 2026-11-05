<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support;

use App\Modules\Compensation\Enums\AssumptionKey;
use App\Modules\Compensation\Enums\RaiseUnit;
use App\Modules\Compensation\Models\Benefit;
use App\Modules\Compensation\Models\ContractTerm;
use App\Modules\Compensation\Models\Scenario;
use App\Modules\Compensation\Support\Calculation\Money;
use App\Modules\Compensation\Support\Calculation\ScenarioCost;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Carbon\CarbonImmutable;

/** What the editor of a scenario needs to draw it: the scenario as the form types it, the assumptions, the states, the defaults of a new one and the other scenarios it is weighed against. */
final readonly class ScenarioEditor
{
    /** The most other scenarios the editor weighs this one against. */
    public const int OTHERS = 4;

    public function __construct(private WorkCenters $workCenters, private ScenarioInputBuilder $inputs) {}

    /**
     * @return array<string, mixed>
     */
    public function props(?Scenario $scenario, CarbonImmutable $today): array
    {
        $states = [];

        foreach ($this->workCenters->states() as $code => $label) {
            $states[] = ['value' => $code, 'label' => $label];
        }

        return [
            'scenario' => $scenario === null ? null : $this->scenario($scenario),
            'assumptions' => $this->assumptions($scenario === null ? AssumptionKey::defaultSnapshot() : $scenario->assumptions),
            'states' => $states,
            'defaults' => ['effective_on' => $today->addYear()->startOfYear()->toDateString(), 'horizon_years' => 3],
            'horizons' => [3, 4, 5],
            'others' => $this->others($scenario),
        ];
    }

    /**
     * The latest active scenarios but this one, with what each costs in its first year: «Frente a tus otros escenarios».
     *
     * @return list<array{id: string, name: string, first_year_cents: int, first_year_text: string}>
     */
    private function others(?Scenario $scenario): array
    {
        $others = Scenario::query()
            ->whereNull('archived_at')
            ->when($scenario !== null, fn ($query) => $query->whereKeyNot($scenario?->id))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->limit(self::OTHERS)
            ->get();

        $rows = [];

        foreach ($others as $other) {
            $cents = ScenarioCost::firstYear($this->inputs->build($other))->firstYearCents();
            $rows[] = ['id' => $other->id, 'name' => $other->name, 'first_year_cents' => $cents, 'first_year_text' => Money::pesos($cents)];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function scenario(Scenario $scenario): array
    {
        return [
            'id' => $scenario->id,
            'name' => $scenario->name,
            'effective_on' => $scenario->effective_on->toDateString(),
            'retroactive_months' => $scenario->retroactive_months,
            'horizon_years' => $scenario->horizon_years,
            'raise_unit' => $scenario->raise_unit->value,
            'unionized_raise' => $this->raise($scenario, $scenario->group_changes['unionized'] ?? 0),
            'trusted_raise' => ($scenario->group_changes['trusted'] ?? null) === null ? '' : $this->raise($scenario, $scenario->group_changes['trusted']),
            'default_state' => $scenario->default_state ?? '',
            'archived' => $scenario->archived_at !== null,
            'term_changes' => $this->termChanges($scenario),
        ];
    }

    /** A raise as the editor types it: a percent ("5.75") or an amount a month ("608.5"). */
    private function raise(Scenario $scenario, int $value): string
    {
        return $scenario->raise_unit === RaiseUnit::Amount ? BenefitValue::decimal($value) : PercentBasisPoints::format($value);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function termChanges(Scenario $scenario): array
    {
        $ids = array_map(fn (array $change): string => $change['benefit_id'], $scenario->term_changes);
        $benefits = [];

        foreach (Benefit::query()->whereIn('id', $ids)->get() as $benefit) {
            $benefits[$benefit->id] = $benefit;
        }

        $current = [];

        foreach (ContractTerm::query()->whereIn('benefit_id', $ids)->where('effective_from', '<=', $scenario->effective_on->toDateString())->orderBy('effective_from')->get() as $term) {
            $current[$term->benefit_id] = $term;
        }

        $changes = [];

        foreach ($scenario->term_changes as $change) {
            $benefit = $benefits[$change['benefit_id']] ?? null;

            if ($benefit === null) {
                continue;
            }

            $term = $current[$benefit->id] ?? null;
            $changes[] = [
                'benefit_id' => $benefit->id,
                'name' => $benefit->name,
                'unit' => $benefit->kind->unit(),
                'value' => BenefitValue::decimal($change['value']),
                'current_text' => $term === null ? null : BenefitValue::format($benefit->kind, $term->value),
                'tenure_table' => array_map(fn (array $row): array => ['from_years' => $row['from_years'], 'value' => BenefitValue::decimal($row['value'])], $change['tenure_table'] ?? []),
            ];
        }

        return $changes;
    }

    /**
     * @param  array<string, array{value_bp: int, source: string, set_on: string}>  $snapshot
     * @return list<array{key: string, label: string, value: string, source: string, set_on: string}>
     */
    private function assumptions(array $snapshot): array
    {
        $assumptions = [];

        foreach (AssumptionKey::cases() as $key) {
            $assumption = $snapshot[$key->value] ?? AssumptionKey::defaultSnapshot()[$key->value];
            $assumptions[] = ['key' => $key->value, 'label' => $key->label(), 'value' => PercentBasisPoints::format($assumption['value_bp']), 'source' => $assumption['source'], 'set_on' => $assumption['set_on']];
        }

        return $assumptions;
    }
}
