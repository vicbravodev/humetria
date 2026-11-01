<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support;

use App\Modules\Telework\Enums\ChecklistStatus;
use App\Modules\Telework\Models\Checklist;
use App\Modules\Telework\Models\ChecklistQuestion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The order of a draft's questions: positions are global per checklist (1..n, by section then position). Every edit that
 * adds, removes or moves a question goes through here, inside a transaction that locks the checklist.
 */
final class ChecklistOrder
{
    /**
     * Locks the draft that owns `$checklistId` and runs `$change` on it; a published checklist never changes.
     *
     * @template T
     *
     * @param  callable(Checklist): T  $change
     * @return T
     *
     * @throws ValidationException when the checklist is already published.
     */
    public static function editing(string $checklistId, callable $change): mixed
    {
        return DB::transaction(function () use ($checklistId, $change): mixed {
            $checklist = Checklist::query()->lockForUpdate()->findOrFail($checklistId);

            if ($checklist->status !== ChecklistStatus::Draft) {
                throw ValidationException::withMessages(['checklist' => 'Esta versión ya está publicada: inicia un borrador nuevo para cambiarla.']);
            }

            return $change($checklist);
        });
    }

    /** @return list<string> Question ids in order. */
    public static function ids(Checklist $checklist): array
    {
        return array_values(ChecklistQuestion::query()->where('checklist_id', $checklist->id)->orderBy('position')->get(['id'])->map(fn (ChecklistQuestion $question): string => $question->id)->all());
    }

    /**
     * Renumbers the checklist 1..n following `$ids`. Two phases, so the unique (checklist, position) never meets a duplicate
     * halfway: first every row goes negative (out of the way of every final place), then each lands on its final place.
     *
     * @param  list<string>  $ids
     */
    public static function renumber(Checklist $checklist, array $ids): void
    {
        // Phase 1 needs negative positions: the check is `position <> 0`, see the migration that relaxed it.
        // ponytail: one UPDATE per question (about 120); a CASE statement if lists ever grow into the thousands.
        ChecklistQuestion::query()->where('checklist_id', $checklist->id)->update(['position' => DB::raw('-position')]);

        foreach ($ids as $index => $id) {
            ChecklistQuestion::query()->where('checklist_id', $checklist->id)->where('id', $id)->update(['position' => $index + 1]);
        }
    }
}
