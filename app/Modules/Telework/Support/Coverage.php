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
        $covered = ChecklistQuestion::query()->where('checklist_id', $checklist->id)->whereNotNull('requirement')->get(['section', 'requirement']);
        $missing = [];

        foreach ([1, 2] as $section) {
            $have = $covered->where('section', $section)->map(fn (ChecklistQuestion $question): ?Requirement => $question->requirement)->all();
            $missing[$section] = array_values(array_filter(Requirement::cases(), fn (Requirement $requirement): bool => ! in_array($requirement, $have, true)));
        }

        return $missing;
    }
}
