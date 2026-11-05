<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Actions;

use App\Modules\Compensation\Models\Scenario;
use Illuminate\Support\Facades\Log;

final readonly class ArchiveScenario
{
    /** Takes a scenario out of the active list without deleting it: a scenario is never deleted. */
    public function handle(Scenario $scenario): Scenario
    {
        $scenario->update(['archived_at' => now()]);
        Log::info('compensation.scenario_archived', ['scenario_id' => $scenario->id]);

        return $scenario;
    }
}
