<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

use App\Actions\RecordAudit;
use App\Modules\Telework\Enums\NoticeKind;
use App\Modules\Telework\Models\WorkerNotice;
use App\Modules\Telework\Support\FileStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Closes an accident notice once it is resolved (rediseño v2, board V3-Aviso): after what was done was recorded, with its
 * evidence (a signed agreement as PDF, or a photo of the change). Once: the closing is evidence for the dossier. Who may close is the controller's business (`NoticeAccess`). Audit and log carry ids.
 */
final readonly class CloseNotice
{
    public function __construct(private FileStore $files, private RecordAudit $audit) {}

    /**
     * @param  string  $path  the evidence, already stored (`NoticeEvidence`): whatever happens, a closing that is not saved
     *                        leaves no file behind.
     *
     * @throws ValidationException when it is not an accident, nothing was recorded yet, or it is closed.
     */
    public function handle(WorkerNotice $notice, string $path, int $userId): WorkerNotice
    {
        try {
            $this->closable($notice);

            return DB::transaction(function () use ($notice, $path, $userId): WorkerNotice {
                $locked = WorkerNotice::query()->lockForUpdate()->findOrFail($notice->id);
                $this->closable($locked);
                $locked->update(['closed_by' => $userId, 'closed_at' => now(), 'evidence_path' => $path]);

                $record = ['notice_id' => $locked->id, 'teleworker_id' => $locked->teleworker_id];
                $this->audit->handle('telework.notice_closed', $locked, $record);
                Log::info('telework.notice_closed', $record);

                return $locked;
            });
        } catch (Throwable $failure) {
            $this->files->delete($path);

            throw $failure;
        }
    }

    /** @throws ValidationException */
    private function closable(WorkerNotice $notice): void
    {
        $why = match (true) {
            $notice->kind !== NoticeKind::WorkRisk => 'Solo un aviso de accidente se cierra con evidencia.',
            $notice->reviewed_at === null => 'Primero registra qué se hizo.',
            $notice->closed_at !== null => 'Este aviso ya está cerrado.',
            default => null,
        };

        if ($why !== null) {
            throw ValidationException::withMessages(['notice' => $why]);
        }
    }
}
