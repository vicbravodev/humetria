<?php

declare(strict_types=1);

namespace App\Modules\Seating\Actions;

use App\Modules\Seating\Enums\AssessmentStatus;
use App\Modules\Seating\Models\RiskAssessment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Saves what the Comisión changed in a draft: some scores, the other aspects (Quinta C X) and the verification date. A
 * signed assessment is final (criterio C-17).
 */
final readonly class SaveAssessmentDraft
{
    /**
     * @param  array<string, int>  $scores  Only the conditions that changed.
     * @param  array{other_aspects?: string|null, verified_on?: string|null}  $details  Only the ones that were sent.
     *
     * @throws ValidationException when the assessment is already signed.
     */
    public function handle(RiskAssessment $assessment, array $scores, array $details): RiskAssessment
    {
        return DB::transaction(function () use ($assessment, $scores, $details): RiskAssessment {
            $locked = RiskAssessment::query()->whereKey($assessment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === AssessmentStatus::Signed) {
                throw ValidationException::withMessages(['status' => 'Esta evaluación ya está firmada y no se edita: evalúa de nuevo para registrar un cambio.']);
            }

            $locked->scores = [...$locked->scores, ...$scores];

            if (array_key_exists('other_aspects', $details)) {
                $locked->other_aspects = $details['other_aspects'];
            }

            if (array_key_exists('verified_on', $details)) {
                $locked->setAttribute('verified_on', $details['verified_on']);
            }

            $locked->save();
            Log::info('seating.assessment_saved', ['assessment_id' => $locked->id, 'work_center_id' => $locked->work_center_id]);

            return $locked;
        });
    }
}
