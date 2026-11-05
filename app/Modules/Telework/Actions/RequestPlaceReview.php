<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

use App\Modules\Telework\Enums\NoticeKind;
use App\Modules\Telework\Enums\Section;
use App\Modules\Telework\Jobs\SendPortalReminder;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Telework\Models\WorkerNotice;
use App\Modules\Telework\Models\Workplace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * After an accident, asks the Comisión to review the place again (rediseño v2, board V3-Aviso): the person is asked for the
 * Sección II of the list of the place they work from, and the Comisión validates it as any other. Asking again is
 * harmless: the list already waiting is the answer, and the notice keeps the day it was first asked.
 */
final readonly class RequestPlaceReview
{
    public function __construct(private RequestChecklist $request) {}

    /** @throws ValidationException when it is not an accident, the person has no place in use, or there is no published list. */
    public function handle(WorkerNotice $notice): WorkerNotice
    {
        if ($notice->kind !== NoticeKind::WorkRisk) {
            throw ValidationException::withMessages(['notice' => 'Solo después de un accidente se pide revisar el lugar.']);
        }

        $workplace = Workplace::query()->where('teleworker_id', $notice->teleworker_id)->whereNull('retired_at')->orderByDesc('created_at')->orderByDesc('id')->first();

        if ($workplace === null) {
            throw ValidationException::withMessages(['notice' => 'La persona no tiene un lugar de trabajo en uso.']);
        }

        return DB::transaction(function () use ($notice, $workplace): WorkerNotice {
            $application = $this->request->handle($workplace, Section::Two);
            $locked = WorkerNotice::query()->lockForUpdate()->findOrFail($notice->id);

            if ($locked->place_review_requested_at === null) {
                $locked->update(['place_review_requested_at' => now()]);
            }

            Log::info('telework.place_review_requested', ['notice_id' => $locked->id, 'workplace_id' => $workplace->id, 'application_id' => $application->id]);
            SendPortalReminder::remind($locked->tenant_id, Teleworker::query()->findOrFail($locked->teleworker_id, ['employee_id'])->employee_id);

            return $locked;
        });
    }
}
