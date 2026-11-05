<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support;

use App\Modules\Compensation\Models\Scenario;
use App\Modules\Compensation\Support\Calculation\Money;
use App\Modules\Compensation\Support\Calculation\ScenarioCost;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * A page of scenarios as the list shows it: what each proposes and what it costs in its first year. This is a place, besides
 * the report, where Compensación sends money to a page.
 */
final readonly class ScenarioRows
{
    public function __construct(private ScenarioInputBuilder $inputs) {}

    /**
     * @param  LengthAwarePaginator<int, Scenario>  $page
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function through(LengthAwarePaginator $page): LengthAwarePaginator
    {
        return $page->through(fn (Scenario $scenario): array => $this->row($scenario));
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Scenario $scenario): array
    {
        $cost = ScenarioCost::firstYear($this->inputs->build($scenario));
        $trusted = $scenario->group_changes['trusted'] ?? null;

        return [
            'id' => $scenario->id,
            'name' => $scenario->name,
            'effective_on' => $scenario->effective_on->toDateString(),
            'horizon_years' => $scenario->horizon_years,
            'retroactive_months' => $scenario->retroactive_months,
            'unionized_raise' => PercentBasisPoints::format($scenario->group_changes['unionized'] ?? 0),
            'trusted_raise' => $trusted === null ? null : PercentBasisPoints::format($trusted),
            'raise_text' => RaiseText::of($scenario),
            'changed_benefits' => count($scenario->term_changes),
            'people' => $cost->people,
            'first_year_cents' => $cost->firstYearCents(),
            'first_year_text' => Money::pesos($cost->firstYearCents()),
            'recurring_text' => Money::pesos($cost->recurringCents),
            'over_today_text' => ScenarioPreview::share($cost->firstYearCents(), $cost->beforeCents),
            'archived' => $scenario->archived_at !== null,
        ];
    }
}
