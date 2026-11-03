<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Actions;

use App\Actions\RecordAudit;
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
 * HR cancels an action with a reason (criterio C-35): it is never deleted, it stays in the Programa as «cancelada» and the
 * cancellation is a step of its log. A done action is not cancelled (reopen it first) and a cancelled one stays so. The
 * reason is HR's text: it is kept, but goes to neither the log nor the audit trail.
 */
final readonly class CancelAction
{
    public function __construct(private CurrentTenant $tenant, private RecordAudit $audit) {}

    /**
     * @throws ValidationException
     * @throws ModelNotFoundException when the action is not of this company.
     */
    public function handle(string $actionId, string $reason, int $userId): ProgramAction
    {
        $action = ProgramAction::query()->findOrFail($actionId);
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Escribe el motivo de la cancelación.']);
        }

        if (! in_array($action->status, [ActionStatus::Planned, ActionStatus::InProgress], true)) {
            throw ValidationException::withMessages(['status' => 'Solo se cancela una acción planeada o en curso.']);
        }

        return DB::transaction(function () use ($action, $reason, $userId): ProgramAction {
            ProgressEntry::query()->create([
                'tenant_id' => $this->tenant->id(),
                'action_id' => $action->id,
                'recorded_on' => ProgramClock::today(),
                'status' => ActionStatus::Cancelled,
                'note' => $reason,
                'recorded_by' => $userId,
            ]);
            $action->update(['status' => ActionStatus::Cancelled, 'cancelled_reason' => $reason]);
            Log::info('intervention.action_cancelled', ['action_id' => $action->id, 'program_id' => $action->program_id]);
            $this->audit->handle('interventions.action_cancelled', $action, ['action_id' => $action->id, 'program_id' => $action->program_id]);

            return $action;
        });
    }
}
