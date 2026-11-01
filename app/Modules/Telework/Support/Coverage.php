<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support;

use App\Modules\Instruments\Data\Requirement;
use App\Modules\Telework\Models\Checklist;
use App\Modules\Telework\Models\ChecklistQuestion;

/**
 * Which requirements of chapter 7 and numeral 5.1.1 a checklist leaves without a question in each section (criterio C-08).
 * The inspection checks them, so the Comisión is warned before publishing; the decision to publish anyway is theirs.
 */
final readonly class Coverage
{
    /** @return array<int, list<Requirement>> Section (1 and 2) to the requirements it has no question for. */
    public function missing(Checklist $checklist): array
    {
        // missingMany() answers for every list it is given.
        return $this->missingMany([$checklist])[$checklist->id];
    }

    /**
     * The same for several checklists, in one query.
     *
     * @param  list<Checklist>  $checklists
     * @return array<string, array<int, list<Requirement>>> by checklist id
     */
    public function missingMany(array $checklists): array
    {
        $covered = [];

        foreach (ChecklistQuestion::query()->whereIn('checklist_id', array_map(fn (Checklist $checklist): string => $checklist->id, $checklists))->whereNotNull('requirement')->get(['checklist_id', 'section', 'requirement']) as $question) {
            if ($question->requirement !== null) {
                $covered[$question->checklist_id][$question->section][] = $question->requirement;
            }
        }

        $missing = [];

        foreach ($checklists as $checklist) {
            foreach ([1, 2] as $section) {
                $have = $covered[$checklist->id][$section] ?? [];
                $missing[$checklist->id][$section] = array_values(array_filter(Requirement::cases(), fn (Requirement $requirement): bool => ! in_array($requirement, $have, true)));
            }
        }

        return $missing;
    }
}
