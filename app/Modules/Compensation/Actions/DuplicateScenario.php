<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Actions;

use App\Modules\Compensation\Models\Scenario;
use Illuminate\Support\Facades\Log;

final readonly class DuplicateScenario
{
    private const int NAME_LENGTH = 120;

    /** A copy to try another variation from, with the same changes and assumptions and an active state. */
    public function handle(Scenario $scenario): Scenario
    {
        $copy = Scenario::query()->create([
            'tenant_id' => $scenario->tenant_id,
            'name' => mb_substr('Copia de '.$scenario->name, 0, self::NAME_LENGTH),
            'effective_on' => $scenario->effective_on,
            'retroactive_months' => $scenario->retroactive_months,
            'horizon_years' => $scenario->horizon_years,
            'raise_unit' => $scenario->raise_unit,
            'group_changes' => $scenario->group_changes,
            'term_changes' => $scenario->term_changes,
            'assumptions' => $scenario->assumptions,
            'default_state' => $scenario->default_state,
        ]);

        Log::info('compensation.scenario_duplicated', ['scenario_id' => $copy->id, 'from_scenario_id' => $scenario->id]);

        return $copy;
    }
}
