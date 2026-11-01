<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

use App\Modules\Telework\Models\Checklist;
use App\Modules\Telework\Models\ChecklistQuestion;
use App\Modules\Telework\Support\ChecklistOrder;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Takes a question out of the draft (the one deletion NOM-037 allows: published versions are evidence) and closes the gap.
 */
final readonly class RemoveQuestion
{
    /** @throws ValidationException when the checklist is already published. */
    public function handle(ChecklistQuestion $question): void
    {
        ChecklistOrder::editing($question->checklist_id, function (Checklist $checklist) use ($question): void {
            $question->delete();
            ChecklistOrder::renumber($checklist, ChecklistOrder::ids($checklist));
            Log::info('telework.question_removed', ['checklist_id' => $checklist->id, 'question_id' => $question->id]);
        });
    }
}
