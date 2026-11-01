<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

use App\Modules\Telework\Models\Checklist;
use App\Modules\Telework\Models\ChecklistQuestion;
use App\Modules\Telework\Support\ChecklistOrder;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Moves a question one place up or down inside its section. At the edge of the section nothing happens.
 */
final readonly class MoveQuestion
{
    /** @throws ValidationException when the checklist is already published. */
    public function handle(ChecklistQuestion $question, bool $up): void
    {
        ChecklistOrder::editing($question->checklist_id, function (Checklist $checklist) use ($question, $up): void {
            $ids = ChecklistOrder::ids($checklist);
            $from = array_search($question->id, $ids, true);
            $to = $up ? $from - 1 : $from + 1;
            // The question was found in its own list, so $from is never false here: the checks only narrow the type.
            $neighbour = $from === false ? null : ChecklistQuestion::query()->where('checklist_id', $checklist->id)->where('id', $ids[$to] ?? null)->first(['section']); // @pest-mutate-ignore: FalseToTrue

            if ($from === false || $neighbour === null || $neighbour->section !== $question->section) { // @pest-mutate-ignore: FalseToTrue
                return;
            }

            array_splice($ids, $from, 1);
            array_splice($ids, $to, 0, [$question->id]);
            ChecklistOrder::renumber($checklist, $ids);
            Log::info('telework.question_moved', ['checklist_id' => $checklist->id, 'question_id' => $question->id, 'direction' => $up ? 'up' : 'down']);
        });
    }
}
