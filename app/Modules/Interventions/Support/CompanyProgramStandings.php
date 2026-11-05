<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Support;

use App\Modules\Interventions\Contracts\ProgramStandings;
use App\Modules\Interventions\Models\Program;
use App\Modules\Interventions\Models\ProgramAction;
use App\Modules\Interventions\Models\ProgramItem;

/** Three reads, whatever the number of evaluations, items or actions. */
final readonly class CompanyProgramStandings implements ProgramStandings
{
    public function forEvaluations(array $campaignIds): array
    {
        if ($campaignIds === []) {
            return [];
        }

        $programs = Program::query()->whereIn('campaign_id', $campaignIds)->get(['id', 'campaign_id']);
        $items = ProgramItem::query()->whereIn('program_id', $programs->modelKeys())->get();
        $actions = ProgramAction::query()->whereIn('program_id', $programs->modelKeys())->get();
        $today = ProgramClock::today();
        $states = [];

        foreach ($programs as $program) {
            $states[$program->campaign_id] = ProgramProgress::standing(
                array_values($items->where('program_id', $program->id)->all()),
                array_values($actions->where('program_id', $program->id)->all()),
                $today,
            )['state']->value;
        }

        return $states;
    }
}
