<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support;

use App\Modules\Compensation\Enums\AssumptionKey;
use App\Modules\Compensation\Models\Benefit;
use App\Modules\Compensation\Models\ContractTerm;
use App\Modules\Compensation\Models\Scenario;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Carbon\CarbonImmutable;

/** What the editor of a scenario needs to draw it: the scenario as the form types it, the assumptions, the states and the defaults of a new one. */
final readonly class ScenarioEditor
{
    public function __construct(private WorkCenters $workCenters) {}

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
        ];
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
            'unionized_raise' => PercentBasisPoints::format($scenario->group_changes['unionized'] ?? 0),
            'trusted_raise' => ($scenario->group_changes['trusted'] ?? null) === null ? '' : PercentBasisPoints::format($scenario->group_changes['trusted']),
            'default_state' => $scenario->default_state ?? '',
            'archived' => $scenario->archived_at !== null,
            'term_changes' => $this->termChanges($scenario),
        ];
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
