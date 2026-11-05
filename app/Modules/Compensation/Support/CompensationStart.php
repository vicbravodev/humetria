<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support;

use App\Modules\Compensation\Enums\PayGroup;
use App\Modules\Compensation\Models\Scenario;
use App\Modules\Compensation\Support\Calculation\Money;
use App\Modules\Compensation\Support\Calculation\ScenarioCost;
use App\Modules\Compensation\Support\Calculation\Sensitivity;
use Carbon\CarbonImmutable;

/**
 * The contract as it is today, costed like a scenario with no change: what the payroll with its charges costs a year
 * («Hoy pagas…») and, for the negotiating table, about what a point of raise to each group costs a year (none for a group
 * with nobody in it).
 */
final readonly class CompensationStart
{
    public function __construct(private ScenarioInputBuilder $inputs) {}

    /**
     * @return array{today: array{people: int, cost_text: string}, negotiation: array{unionized_point_text: string, trusted_point_text: ?string}}
     */
    public function today(CarbonImmutable $today): array
    {
        $input = $this->inputs->build(new Scenario([
            'effective_on' => $today, 'retroactive_months' => 0, 'horizon_years' => 3, 'group_changes' => [], 'term_changes' => [], 'default_state' => null,
        ]));
        $cost = ScenarioCost::firstYear($input);
        $trusted = Sensitivity::pointFor($input, PayGroup::Trusted);

        return [
            'today' => ['people' => $cost->people, 'cost_text' => Money::pesos($cost->beforeCents)],
            'negotiation' => [
                'unionized_point_text' => Money::about(Sensitivity::pointFor($input, PayGroup::Unionized)),
                'trusted_point_text' => $trusted === 0 ? null : Money::about($trusted),
            ],
        ];
    }
}
