<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Actions;

use App\Modules\Campaigns\Contracts\CampaignRecords;
use App\Modules\Campaigns\Data\CampaignRecord;
use App\Modules\Interventions\Models\Program;
use App\Modules\Interventions\Models\ProgramItem;
use App\Modules\Interventions\Support\ProgramClock;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * HR schedules the evaluation that follows the measures of a row and records it once done (num. 8.4 e, "en su caso"): a day,
 * what was found (text) and, if it exists, the closed evaluation of the same work center that came after this one. It is a
 * note with a date: nothing is compared automatically against the earlier evaluation.
 */
final readonly class RecordFollowUp
{
    public function __construct(private CampaignRecords $campaigns) {}

    /**
     * @throws ValidationException
     * @throws ModelNotFoundException when the row is not of this company.
     */
    public function handle(string $itemId, ?string $dueOn, ?string $doneOn, ?string $result, ?string $evaluationId): ProgramItem
    {
        $item = ProgramItem::query()->findOrFail($itemId);
        $program = Program::query()->findOrFail($item->program_id);
        $text = trim($result ?? '');
        $errors = [];

        if ($doneOn !== null && $doneOn > ProgramClock::today()) {
            $errors['follow_up_done_on'] = 'La fecha no puede ser futura.';
        } elseif ($doneOn !== null && $text === '') {
            $errors['follow_up_result'] = 'Escribe qué encontró la evaluación posterior.';
        }

        if ($evaluationId !== null && ! $this->isLater($program, $evaluationId)) {
            $errors['follow_up_campaign_id'] = 'Elige una evaluación de la lista.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $item->update([
            'follow_up_due_on' => $dueOn,
            'follow_up_done_on' => $doneOn,
            'follow_up_result' => $text === '' ? null : $text,
            'follow_up_campaign_id' => $evaluationId,
        ]);
        Log::info('intervention.follow_up_recorded', ['item_id' => $item->id, 'program_id' => $program->id, 'done' => $doneOn !== null, 'with_evaluation' => $evaluationId !== null]);

        return $item;
    }

    /** Whether it is a closed evaluation of the Programa's work center that closed after the Programa's own. */
    private function isLater(Program $program, string $evaluationId): bool
    {
        $campaign = $this->campaigns->find($program->campaign_id);

        if ($campaign === null) {
            return false;
        }

        // Latest first: the later ones are those ahead of the Programa's own evaluation.
        $closed = array_map(fn (CampaignRecord $record): string => $record->id, $this->campaigns->closedAt($campaign->workCenterId));
        $own = array_search($campaign->id, $closed, true);

        return $own !== false && in_array($evaluationId, array_slice($closed, 0, $own), true);
    }
}
