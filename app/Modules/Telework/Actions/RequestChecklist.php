<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

use App\Modules\Telework\Enums\Section;
use App\Modules\Telework\Models\Checklist;
use App\Modules\Telework\Models\ChecklistApplication;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Telework\Models\Workplace;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Asks a person for a section of the checklist of a workplace, with the latest published version of their work center's
 * list. While one is still waiting to be answered, that one is the answer: nobody gets the same list twice.
 */
final readonly class RequestChecklist
{
    /** @throws ValidationException when the work center has no published list yet. */
    public function handle(Workplace $workplace, Section $section): ChecklistApplication
    {
        $pending = fn (): ?ChecklistApplication => ChecklistApplication::query()->where('workplace_id', $workplace->id)->where('section', $section)->whereNull('submitted_at')->whereNull('cancelled_at')->first();
        $existing = $pending();

        if ($existing !== null) {
            return $existing;
        }

        $teleworker = Teleworker::query()->findOrFail($workplace->teleworker_id, ['work_center_id']);
        $checklist = Checklist::latestPublished($teleworker->work_center_id) ?? throw ValidationException::withMessages(['work_center' => 'La Comisión aún no publica la lista']);

        try {
            // Its own savepoint: a violation aborts the caller's Postgres transaction unless it is rolled back to here.
            $application = DB::transaction(fn (): ChecklistApplication => ChecklistApplication::query()->create([
                'tenant_id' => $workplace->tenant_id,
                'workplace_id' => $workplace->id,
                'checklist_id' => $checklist->id,
                'section' => $section,
                'requested_at' => now(),
            ]));
        } catch (UniqueConstraintViolationException $race) {
            // Another request asked at the same moment: theirs stands.
            return $pending() ?? throw $race;
        }

        Log::info('telework.checklist_requested', ['application_id' => $application->id, 'workplace_id' => $workplace->id, 'section' => $section->value, 'checklist_id' => $checklist->id]);

        return $application;
    }
}
