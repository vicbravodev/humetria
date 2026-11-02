<?php

declare(strict_types=1);

namespace App\Modules\Seating\Actions;

use App\Actions\RecordAudit;
use App\Modules\Instruments\Contracts\SeatingAssessment;
use App\Modules\Seating\Data\PositionData;
use App\Modules\Seating\Enums\Sector;
use App\Modules\Seating\Models\SeatingProfile;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Saves a standing position. The result of Figura 1 is derived from the three answers by the instrument (criterio C-06) and
 * stored with `figure_one_applies` (more than three hours standing, C-05); nothing the browser posts decides them. The
 * nature of the work counts only in an industrial center (C-03): there, a position whose nature does not allow seating
 * needs its reason in writing and has no result.
 */
final readonly class SavePosition
{
    private const int PROLONGED_AFTER_MINUTES = 180;

    public function __construct(private CurrentTenant $tenant, private SeatingAssessment $assessment, private RecordAudit $audit) {}

    /**
     * @throws ValidationException when an industrial position refuses seating without a reason.
     */
    public function handle(PositionData $data): StandingPosition
    {
        $industrial = SeatingProfile::query()->where('work_center_id', $data->workCenterId)->first()?->sector === Sector::Industrial;
        $natureAllows = $industrial ? $data->natureAllows : null;
        $reason = $natureAllows === false ? trim($data->natureReason ?? '') : null;

        if ($reason === '') {
            throw ValidationException::withMessages(['nature_reason' => 'Escribe por qué la naturaleza del trabajo no permite el asiento.']);
        }

        $placement = $natureAllows === false ? null : $this->assessment->placement($data->canWorkSeated, $data->hasSpaceForSeat, $data->needsToBeNearby);

        return DB::transaction(function () use ($data, $natureAllows, $reason, $placement): StandingPosition {
            $position = ($data->id === null ? new StandingPosition : StandingPosition::query()->findOrFail($data->id))->fill([
                'tenant_id' => $this->tenant->id(),
                'work_center_id' => $data->workCenterId,
                'name' => $data->name,
                'tasks' => $data->tasks,
                'bipedestation_type' => $data->type,
                'continuous_minutes' => $data->continuousMinutes,
                'can_work_seated' => $data->canWorkSeated,
                'has_space_for_seat' => $data->hasSpaceForSeat,
                'needs_to_be_nearby' => $data->needsToBeNearby,
                'placement' => $placement,
                'figure_one_applies' => $data->continuousMinutes > self::PROLONGED_AFTER_MINUTES,
                'max_people_at_once' => $data->maxPeopleAtOnce,
                'area_conditions' => $data->areaConditions,
                'tools' => $data->tools,
                'physical_load' => $data->physicalLoad,
                'attention_level' => $data->attentionLevel,
                'default_standing_time' => $data->defaultStandingTime,
                'default_posture_change' => $data->defaultPostureChange,
                'default_surface' => $data->defaultSurface,
                'default_movement_space' => $data->defaultMovementSpace,
                'default_pauses' => $data->defaultPauses,
                'nature_allows' => $natureAllows,
                'nature_reason' => $reason,
            ]);
            $position->save();

            $record = ['work_center_id' => $data->workCenterId, 'position_id' => $position->id, 'placement' => $placement?->value];
            $this->audit->handle('seating.position_saved', $position, $record);
            Log::info('seating.position_saved', $record);

            return $position;
        });
    }
}
