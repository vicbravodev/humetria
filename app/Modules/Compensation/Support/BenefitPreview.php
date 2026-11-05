<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support;

use App\Modules\Compensation\Actions\SaveBenefit;
use App\Modules\Compensation\Data\BenefitData;
use App\Modules\Compensation\Data\CostSubject;
use App\Modules\Compensation\Models\Benefit;
use App\Modules\Compensation\Models\Scenario;
use App\Modules\Compensation\Support\Calculation\BenefitCost;
use App\Modules\Compensation\Support\Calculation\Money;
use App\Modules\Compensation\Support\Calculation\Rate;
use App\Modules\Compensation\Support\Calculation\ScenarioCost;
use App\Modules\Compensation\Support\Calculation\TenureYears;
use Carbon\CarbonImmutable;

/**
 * What a benefit that is being typed costs today (board V4-Comp-Prestacion, decision A14): the contract of today with the
 * benefit added, costed by the same engine as a scenario, with the charges it drags. Nothing is saved.
 */
final readonly class BenefitPreview
{
    private const string KEY = 'new-benefit';

    public function __construct(private SaveBenefit $save, private ScenarioInputBuilder $inputs) {}

    /**
     * @param  int  $value  hundredths, see BenefitKind
     * @param  ?list<array{from_years: int, value: int}>  $tenureTable
     * @return array{people: int, yearly_text: string, per_person_month_text: string}
     */
    public function handle(BenefitData $data, int $value, ?array $tenureTable, CarbonImmutable $today): array
    {
        $benefit = new Benefit($this->save->attributes($data));
        $benefit->id = self::KEY;
        $spec = BenefitSpecs::from($benefit, $value, $tenureTable);
        $input = $this->inputs->build(new Scenario([
            'effective_on' => $today, 'retroactive_months' => 0, 'horizon_years' => 3, 'group_changes' => [], 'term_changes' => [], 'default_state' => null,
        ]));
        $yearly = ScenarioCost::firstYear($input->withChanges([], [$spec]))->deltaCents();
        $people = 0;

        foreach (ScenarioCost::units($input) as $unit) {
            $subject = new CostSubject($unit->dailySalaryCents ?? 0, $unit->group, $unit->jobGradeId, TenureYears::at($unit->hiredOn, $today), TenureYears::monthsAt($unit->hiredOn, $today));
            $people += $unit->dailySalaryCents !== null && BenefitCost::applies($spec, $subject) ? $unit->count : 0;
        }

        return [
            'people' => $people,
            'yearly_text' => Money::pesos($yearly),
            'per_person_month_text' => Money::pesos($people === 0 ? 0 : Rate::fraction($yearly, 12 * $people)->of(1)),
        ];
    }
}
