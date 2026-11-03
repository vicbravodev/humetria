<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Actions;

use App\Modules\Interventions\Data\ActionData;
use App\Modules\Interventions\Enums\ActionSubjects;
use App\Modules\Interventions\Models\ActionArea;
use App\Modules\Interventions\Models\ProgramAction;
use App\Modules\Interventions\Models\ProgramItem;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * HR repeats an action in other rows of the Programa: each copy is an independent action (planned, with no history), so the
 * Programa is followed and reported action by action; none is linked to several rows. Every copy goes through SaveAction,
 * so the rules (the third level, the areas shown, the responsible) hold again, and either all are made or none.
 */
final readonly class CopyAction
{
    public function __construct(private SaveAction $save) {}

    /**
     * @param  list<string>  $itemIds  the rows to copy it to, of the same Programa and not its own
     * @return list<ProgramAction>
     *
     * @throws ValidationException
     * @throws ModelNotFoundException when the action, or a row, is not of this company or of its Programa.
     */
    public function handle(string $actionId, array $itemIds, int $userId): array
    {
        $source = ProgramAction::query()->with('areas')->findOrFail($actionId);

        if ($itemIds === [] || count(array_unique($itemIds)) !== count($itemIds) || in_array($source->item_id, $itemIds, true)) {
            throw ValidationException::withMessages(['item_ids' => 'Elige filas distintas de la que ya tiene la acción.']);
        }

        if (ProgramItem::query()->where('program_id', $source->program_id)->whereIn('id', $itemIds)->count() !== count($itemIds)) {
            throw new ModelNotFoundException;
        }

        $areaIds = array_values($source->areas->map(fn (ActionArea $area): string => $area->area_id)->all());

        return DB::transaction(function () use ($source, $itemIds, $areaIds, $userId): array {
            $copies = array_map(fn (string $itemId): ProgramAction => $this->save->handle(new ActionData(
                id: null,
                itemId: $itemId,
                kind: $source->kind,
                level: $source->level,
                title: $source->title,
                description: $source->description,
                subjects: $source->subjects,
                areaIds: $source->subjects === ActionSubjects::Areas ? $areaIds : [],
                startsOn: $source->starts_on?->toDateString(),
                dueOn: $source->due_on->toDateString(),
                responsibleName: $source->responsible_name,
                responsibleUserId: $source->responsible_user_id,
                professional: $source->professional,
            ), $userId), $itemIds);
            Log::info('intervention.action_copied', ['action_id' => $source->id, 'program_id' => $source->program_id, 'copies' => count($copies)]);

            return $copies;
        });
    }
}
