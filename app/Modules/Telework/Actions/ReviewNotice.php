<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

use App\Actions\RecordAudit;
use App\Modules\Telework\Enums\NoticeAction;
use App\Modules\Telework\Enums\NoticeKind;
use App\Modules\Telework\Jobs\SendNoticeAnswer;
use App\Modules\Telework\Models\WorkerNotice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * RH or the Comisión reviews a notice of a person (criterio C-26). Once: a review is evidence and is not rewritten. Who may
 * review is the controller's business (`NoticeAccess`). A notice is reviewed with a note; an accident (6.9; rediseño v2,
 * board V3-Aviso) with what was done, and an optional note, which the person reads in their portal and is told about by
 * email. The note, the actions and the description are personal data: audit and log carry ids only.
 */
final readonly class ReviewNotice
{
    public function __construct(private RecordAudit $audit) {}

    /**
     * @param  list<NoticeAction>  $actions  what was done, only about an accident
     *
     * @throws ValidationException when there is nothing to record, actions about another kind of notice, or the notice
     *                             was already reviewed.
     */
    public function handle(WorkerNotice $notice, string $note, int $userId, array $actions = []): WorkerNotice
    {
        $note = trim($note);

        if ($actions !== [] && $notice->kind !== NoticeKind::WorkRisk) {
            throw ValidationException::withMessages(['actions' => 'Solo un aviso de accidente registra qué se hizo.']);
        }

        if ($note === '' && $actions === []) {
            throw ValidationException::withMessages(['note' => 'Escribe la nota de la revisión.']);
        }

        return DB::transaction(function () use ($notice, $note, $userId, $actions): WorkerNotice {
            $locked = WorkerNotice::query()->lockForUpdate()->findOrFail($notice->id);

            if ($locked->reviewed_at !== null) {
                throw ValidationException::withMessages(['notice' => 'Este aviso ya fue revisado.']);
            }

            $locked->update([
                'reviewed_by' => $userId,
                'reviewed_at' => now(),
                'review_note' => $note === '' ? null : $note,
                'review_actions' => $actions === [] ? null : array_map(fn (NoticeAction $action): string => $action->value, $actions),
            ]);

            $record = ['notice_id' => $locked->id, 'teleworker_id' => $locked->teleworker_id, 'kind' => $locked->kind->value];
            $this->audit->handle('telework.notice_reviewed', $locked, $record);
            Log::info('telework.notice_reviewed', $record);

            if ($actions !== []) {
                SendNoticeAnswer::dispatch($locked->tenant_id, $locked->teleworker_id);
            }

            return $locked;
        });
    }
}
