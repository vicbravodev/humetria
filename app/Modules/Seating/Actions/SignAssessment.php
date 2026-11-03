<?php

declare(strict_types=1);

namespace App\Modules\Seating\Actions;

use App\Actions\RecordAudit;
use App\Modules\Instruments\Contracts\SeatingAssessment;
use App\Modules\Seating\Enums\AssessmentStatus;
use App\Modules\Seating\Events\RiskAssessmentSigned;
use App\Modules\Seating\Models\PositionAssignment;
use App\Modules\Seating\Models\RiskAssessment;
use App\Modules\Seating\Support\AssessmentSnapshot;
use App\Modules\Telework\Contracts\Committee;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Signs an assessment: the platform calculates the total and the level of the DOF's tables, freezes the facts of Quinta C
 * and records who signed and when. Only a member of the center's Comisión signs (criterio C-07), once: under a row lock, a
 * second request finds it signed and leaves it as it is.
 */
final readonly class SignAssessment
{
    public function __construct(private SeatingAssessment $instrument, private Committee $committee, private AssessmentSnapshot $snapshot, private RecordAudit $audit) {}

    /**
     * @throws AuthorizationException when the user does not sit on the center's Comisión.
     * @throws ValidationException when a condition or a datum of Quinta C is missing.
     */
    public function handle(RiskAssessment $assessment, int $userId): RiskAssessment
    {
        if (! $this->committee->isMember($userId, $assessment->work_center_id)) {
            throw new AuthorizationException;
        }

        $signedNow = false;
        $result = DB::transaction(function () use ($assessment, $userId, &$signedNow): RiskAssessment {
            $locked = RiskAssessment::query()->whereKey($assessment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === AssessmentStatus::Signed) {
                return $locked;
            }

            $held = PositionAssignment::query()->where('employee_id', $locked->employee_id)->whereNull('ends_on')->value('position_id');

            if ($held !== $locked->position_id) {
                throw ValidationException::withMessages(['sign' => 'La persona ya no ocupa el puesto de esta evaluación: ábrala de nuevo desde la lista de personas para actualizarla.']);
            }

            $facts = $this->snapshot->capture($locked, $userId);
            $missing = $this->snapshot->missing($locked, $facts);

            try {
                $score = $this->instrument->score($locked->scores);
            } catch (InvalidArgumentException) {
                $missing = ['las siete condicionantes', ...$missing];
                $score = null;
            }

            if ($missing !== [] || $score === null) {
                throw ValidationException::withMessages(['sign' => 'Falta completar para firmar: '.implode(', ', $missing).'.']);
            }

            $locked->forceFill([
                'total' => $score->total,
                'level' => $score->level,
                'instrument_version' => $this->instrument->definition()->version,
                'status' => AssessmentStatus::Signed,
                'snapshot' => $facts,
                'reassessment_due' => false,
                'signed_by' => $userId,
                'signed_at' => now(),
            ])->save();

            // Ids and level only: the scores and the person are the evidence, not something to copy around.
            $record = ['assessment_id' => $locked->id, 'work_center_id' => $locked->work_center_id, 'level' => $score->level->value];
            $this->audit->handle('seating.assessment_signed', $locked, $record);
            Log::info('seating.assessment_signed', $record);
            $signedNow = true;

            return $locked;
        });

        if ($signedNow && $result->level !== null) {
            Event::dispatch(new RiskAssessmentSigned($result->employee_id, $result->work_center_id, $result->level));
        }

        return $result;
    }
}
