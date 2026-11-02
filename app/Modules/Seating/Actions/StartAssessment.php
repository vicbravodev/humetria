<?php

declare(strict_types=1);

namespace App\Modules\Seating\Actions;

use App\Modules\Organization\Contracts\Roster;
use App\Modules\Seating\Enums\AssessmentStatus;
use App\Modules\Seating\Models\PersonInput;
use App\Modules\Seating\Models\PositionAssignment;
use App\Modules\Seating\Models\RiskAssessment;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Opens the assessment of a person who works standing, so that answering it is confirming it (criterio C-07): it starts with
 * the conditions their position gives (a, b, c, e, g) and the person's own latest answers (d, f). Without an answer on
 * discomfort the condition f is 1, and the assessment says so. A person has one draft at a time.
 */
final readonly class StartAssessment
{
    public function __construct(private CurrentTenant $tenant, private Roster $roster) {}

    /** The conditions the position itself gives (a, b, c, e, g): the person's own (d, f) are theirs. */
    private const array POSITION_CONDITIONS = ['standing_time', 'posture_change', 'surface', 'movement_space', 'pauses'];

    /**
     * @throws ValidationException when the person is not active in the center or has no position the law reaches.
     */
    public function handle(string $workCenterId, string $employeeId, int $userId): RiskAssessment
    {
        $person = $this->roster->find($employeeId);
        $assignment = PositionAssignment::query()->where('employee_id', $employeeId)->whereNull('ends_on')->first();
        $position = $assignment === null ? null : StandingPosition::query()->find($assignment->position_id);

        if ($person === null || ! $person->active || $position === null || $position->work_center_id !== $workCenterId) {
            throw ValidationException::withMessages(['employee_id' => 'Esta persona no tiene un puesto de pie asignado en este centro.']);
        }

        if ($position->nature_allows === false) {
            throw ValidationException::withMessages(['employee_id' => 'La naturaleza del trabajo de su puesto no permite el asiento: no se evalúa.']);
        }

        return DB::transaction(function () use ($workCenterId, $employeeId, $userId, $position): RiskAssessment {
            $draft = RiskAssessment::query()->where('employee_id', $employeeId)->where('status', AssessmentStatus::Draft)->first();

            if ($draft !== null) {
                return $draft->position_id === $position->id ? $draft : $this->follow($draft, $position, $workCenterId);
            }

            $input = PersonInput::query()->where('employee_id', $employeeId)->latest('submitted_at')->first();
            $defaults = [
                'standing_time' => $position->default_standing_time,
                'posture_change' => $position->default_posture_change,
                'surface' => $position->default_surface,
                'footwear' => $input?->footwear,
                'movement_space' => $position->default_movement_space,
                'discomfort' => $input->discomfort ?? 1,
                'pauses' => $position->default_pauses,
            ];

            $assessment = RiskAssessment::query()->create([
                'tenant_id' => $this->tenant->id(),
                'employee_id' => $employeeId,
                'position_id' => $position->id,
                'work_center_id' => $workCenterId,
                'scores' => array_filter($defaults, fn (?int $points): bool => $points !== null),
                'status' => AssessmentStatus::Draft,
                'person_provided_health_data' => $input !== null,
                'created_by' => $userId,
            ]);

            Log::info('seating.assessment_started', ['assessment_id' => $assessment->id, 'work_center_id' => $workCenterId]);

            return $assessment;
        });
    }

    /**
     * A draft is evidence of nothing yet, so it goes where the person went: a new position, or a new center, gives it the
     * conditions of the position they hold now (the person's own answers stay). Signing a draft of a position they left would
     * state facts that are no longer theirs.
     */
    private function follow(RiskAssessment $draft, StandingPosition $position, string $workCenterId): RiskAssessment
    {
        $own = array_diff_key($draft->scores, array_flip(self::POSITION_CONDITIONS));
        $given = array_filter([
            'standing_time' => $position->default_standing_time,
            'posture_change' => $position->default_posture_change,
            'surface' => $position->default_surface,
            'movement_space' => $position->default_movement_space,
            'pauses' => $position->default_pauses,
        ], fn (?int $points): bool => $points !== null);

        $draft->update(['position_id' => $position->id, 'work_center_id' => $workCenterId, 'scores' => [...$own, ...$given]]);
        Log::info('seating.assessment_followed_person', ['assessment_id' => $draft->id, 'work_center_id' => $workCenterId]);

        return $draft;
    }
}
