<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

use App\Actions\RecordAudit;
use App\Modules\Telework\Models\WorkerNotice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * RH or the Comisión reviews a notice of a person with a note (criterio C-26). Once: a review is evidence and is not
 * rewritten. Who may review is the controller's business (`NoticeAccess`). The note and the description are personal
 * data: audit and log carry ids only.
 */
final readonly class ReviewNotice
{
    public function __construct(private RecordAudit $audit) {}

    /**
     * @throws ValidationException when the note is blank or the notice was already reviewed.
     */
    public function handle(WorkerNotice $notice, string $note, int $userId): WorkerNotice
    {
        $note = trim($note);

        if ($note === '') {
            throw ValidationException::withMessages(['note' => 'Escribe la nota de la revisión.']);
        }

        return DB::transaction(function () use ($notice, $note, $userId): WorkerNotice {
            $locked = WorkerNotice::query()->lockForUpdate()->findOrFail($notice->id);

            if ($locked->reviewed_at !== null) {
                throw ValidationException::withMessages(['notice' => 'Este aviso ya fue revisado.']);
            }

            $locked->update(['reviewed_by' => $userId, 'reviewed_at' => now(), 'review_note' => $note]);

            $record = ['notice_id' => $locked->id, 'teleworker_id' => $locked->teleworker_id, 'kind' => $locked->kind->value];
            $this->audit->handle('telework.notice_reviewed', $locked, $record);
            Log::info('telework.notice_reviewed', $record);

            return $locked;
        });
    }
}
