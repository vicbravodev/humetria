<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Actions;

use App\Modules\Interventions\Enums\ActionStatus;
use App\Modules\Interventions\Models\ProgramAction;
use App\Modules\Interventions\Models\ProgressEntry;
use App\Modules\Interventions\Support\ProgramClock;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * HR records a step of an action (num. 8.4 d): the day, the state it moves to and a note. The log is insert-only: a step is
 * never edited, and the entry and the action's new state are written together. The moves are planned to running, planned or
 * running to done, done back to running (reopened) and running to running (a note); a cancellation has its own action. The
 * day cannot be in the future.
 */
final readonly class RecordProgress
{
    public function __construct(private CurrentTenant $tenant) {}

    /**
     * @throws ValidationException
     * @throws ModelNotFoundException when the action is not of this company.
     */
    public function handle(string $actionId, ActionStatus $to, string $recordedOn, ?string $note, int $userId): ProgressEntry
    {
        $action = ProgramAction::query()->findOrFail($actionId);
        $from = $action->status;
        $errors = [];

        if ($from === ActionStatus::Cancelled) {
            $errors['status'] = 'Esta acción está cancelada y no recibe avances.';
        } elseif (! self::isForward($from, $to)) {
            $errors['status'] = 'Ese cambio de estado no es válido.';
        }

        if ($recordedOn > ProgramClock::today()) {
            $errors['recorded_on'] = 'La fecha no puede ser futura.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($action, $from, $to, $recordedOn, $note, $userId): ProgressEntry {
            $entry = ProgressEntry::query()->create([
                'tenant_id' => $this->tenant->id(),
                'action_id' => $action->id,
                'recorded_on' => $recordedOn,
                'status' => $to,
                'note' => ($note === null || trim($note) === '') ? null : trim($note),
                'recorded_by' => $userId,
            ]);
            $action->update(['status' => $to, 'completed_on' => $to === ActionStatus::Done ? $recordedOn : null]);
            Log::info('intervention.progress_recorded', ['action_id' => $action->id, 'program_id' => $action->program_id, 'from' => $from->value, 'to' => $to->value]);

            return $entry;
        });
    }

    private static function isForward(ActionStatus $from, ActionStatus $to): bool
    {
        return match ($from) {
            ActionStatus::Planned => in_array($to, [ActionStatus::InProgress, ActionStatus::Done], true),
            ActionStatus::InProgress => in_array($to, [ActionStatus::InProgress, ActionStatus::Done], true),
            ActionStatus::Done => $to === ActionStatus::InProgress,
            ActionStatus::Cancelled => false,
        };
    }
}
