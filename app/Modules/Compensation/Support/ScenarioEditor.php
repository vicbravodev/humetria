<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support;

use App\Modules\Compensation\Enums\AssumptionKey;
use App\Modules\Compensation\Enums\RaiseUnit;
use App\Modules\Compensation\Models\Benefit;
use App\Modules\Compensation\Models\ContractTerm;
use App\Modules\Compensation\Models\Scenario;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Carbon\CarbonImmutable;

/** What the editor of a scenario needs to draw it: the scenario as the form types it, the assumptions, the states, the defaults of a new one and the other scenarios it is weighed against. */
final readonly class ScenarioEditor
{
    public function __construct(private WorkCenters $workCenters, private OtherScenarios $others) {}

    /**
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $prefill  what a new scenario starts with, as a link types it (the point in between of a
     *                                         comparison); anything that is not a valid value is left at its default
     * @return array<string, mixed>
     */
    public function props(?Scenario $scenario, CarbonImmutable $today, array $prefill = []): array
    {
        $states = [];

        foreach ($this->workCenters->states() as $code => $label) {
            $states[] = ['value' => $code, 'label' => $label];
        }

        return [
            'scenario' => $scenario === null ? null : $this->scenario($scenario),
            'assumptions' => $this->assumptions($scenario === null ? AssumptionKey::defaultSnapshot() : $scenario->assumptions),
            'states' => $states,
            'defaults' => ['effective_on' => $today->addYear()->startOfYear()->toDateString(), 'horizon_years' => 3, ...self::prefill($prefill)],
            'horizons' => [3, 4, 5],
            'others' => $this->others->except($scenario),
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
            'raise_unit' => $scenario->raise_unit->value,
            'unionized_raise' => $this->raise($scenario, $scenario->group_changes['unionized'] ?? 0),
            'trusted_raise' => ($scenario->group_changes['trusted'] ?? null) === null ? '' : $this->raise($scenario, $scenario->group_changes['trusted']),
            'default_state' => $scenario->default_state ?? '',
            'archived' => $scenario->archived_at !== null,
            'term_changes' => $this->termChanges($scenario),
        ];
    }

    /**
     * @param  array<string, mixed>  $prefill
     * @return array{name: string, raise_unit: string, unionized_raise: string, trusted_raise: string, retroactive_months: int}
     */
    private static function prefill(array $prefill): array
    {
        $unit = RaiseUnit::tryFrom(is_string($prefill['raise_unit'] ?? null) ? $prefill['raise_unit'] : '') ?? RaiseUnit::Percent;
        $raise = function (string $key) use ($prefill, $unit): ?string {
            $text = is_string($prefill[$key] ?? null) ? trim($prefill[$key]) : '';
            $valid = $unit === RaiseUnit::Amount ? BenefitValue::parse($text) !== null : PercentBasisPoints::parse($text) !== null;

            return $valid ? $text : null;
        };
        $name = is_string($prefill['name'] ?? null) ? trim($prefill['name']) : '';
        $months = is_string($prefill['retroactive_months'] ?? null) && ctype_digit($prefill['retroactive_months']) ? (int) $prefill['retroactive_months'] : 0;

        return [
            'name' => mb_strlen($name) <= 120 ? $name : '',
            'raise_unit' => $unit->value,
            'unionized_raise' => $raise('unionized_raise') ?? '0',
            'trusted_raise' => $raise('trusted_raise') ?? '',
            'retroactive_months' => $months <= 36 ? $months : 0,
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
