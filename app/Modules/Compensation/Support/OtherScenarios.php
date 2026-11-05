<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support;

use App\Modules\Compensation\Models\Scenario;
use App\Modules\Compensation\Support\Calculation\Money;
use App\Modules\Compensation\Support\Calculation\ScenarioCost;

/**
 * The latest active scenarios of the account but one, with what each costs in its first year: what the editor and the report
 * weigh a scenario against. Only this account's: a union is another account, so its proposals never show here (D1).
 */
final readonly class OtherScenarios
{
    /** The most other scenarios a scenario is weighed against. */
    public const int LIMIT = 4;

    public function __construct(private ScenarioInputBuilder $inputs) {}

    /**
     * @return list<array{id: string, name: string, first_year_cents: int, first_year_text: string}>
     */
    public function except(?Scenario $scenario): array
    {
        $others = Scenario::query()
            ->whereNull('archived_at')
            ->when($scenario !== null, fn ($query) => $query->whereKeyNot($scenario?->id))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get();

        $rows = [];

        foreach ($others as $other) {
            $cents = ScenarioCost::firstYear($this->inputs->build($other))->firstYearCents();
            $rows[] = ['id' => $other->id, 'name' => $other->name, 'first_year_cents' => $cents, 'first_year_text' => Money::pesos($cents)];
        }

        return $rows;
    }
}
