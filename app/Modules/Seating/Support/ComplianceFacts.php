<?php

declare(strict_types=1);

namespace App\Modules\Seating\Support;

use App\Modules\Seating\Enums\AssessmentStatus;
use App\Modules\Seating\Enums\DocumentKind;
use App\Modules\Seating\Models\InternalRegulation;
use App\Modules\Seating\Models\MedicalReferral;
use App\Modules\Seating\Models\PauseAgreement;
use App\Modules\Seating\Models\PositionAssignment;
use App\Modules\Seating\Models\PreventiveMeasure;
use App\Modules\Seating\Models\RiskAcknowledgment;
use App\Modules\Seating\Models\RiskAssessment;
use App\Modules\Seating\Models\Seat;
use App\Modules\Seating\Models\SeatingDocument;
use App\Modules\Seating\Models\SeatingProfile;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Telework\Contracts\Committee;
use Illuminate\Support\Collection;

/**
 * Everything the thirteen requirements of a work center read, loaded once and in a fixed number of queries however many
 * positions, people or seats it has.
 */
final readonly class ComplianceFacts
{
    /**
     * @param  Collection<int, StandingPosition>  $positions
     * @param  Collection<int, PositionAssignment>  $assignments  the current ones
     * @param  Collection<int, Seat>  $seats
     * @param  Collection<int, RiskAssessment>  $signed  oldest first
     * @param  array<int, string>  $acknowledged  ids of the assessments confirmed
     * @param  array<int, string>  $referred  ids of the employees with a referral or a note that none was needed
     * @param  Collection<int, PreventiveMeasure>  $measures
     * @param  array<int, string>  $agreed  ids of the positions with a pause agreement
     */
    public function __construct(
        public ?SeatingProfile $profile,
        public Collection $positions,
        public Collection $assignments,
        public Collection $seats,
        public Collection $signed,
        public array $acknowledged,
        public array $referred,
        public Collection $measures,
        public array $agreed,
        public ?InternalRegulation $regulation,
        public bool $hasDiagnosis,
        public bool $hasCommittee,
    ) {}

    public static function of(string $workCenterId, Committee $committee): self
    {
        $positions = StandingPosition::query()->where('work_center_id', $workCenterId)->get(['id', 'nature_allows', 'placement', 'max_people_at_once']);
        $signed = RiskAssessment::query()->where('work_center_id', $workCenterId)->where('status', AssessmentStatus::Signed)->orderBy('signed_at')->orderBy('id')
            ->get(['id', 'employee_id', 'position_id', 'level', 'scores', 'signed_at', 'reassessment_due', 'recorded_in_document_id']);

        return new self(
            SeatingProfile::query()->where('work_center_id', $workCenterId)->first(['id', 'no_standing_positions']),
            $positions,
            PositionAssignment::query()->whereIn('position_id', $positions->pluck('id')->all())->whereNull('ends_on')->get(['employee_id', 'position_id', 'starts_on']),
            Seat::query()->where('work_center_id', $workCenterId)->get(['position_id', 'use', 'quantity', 'condition', 'signposted']),
            $signed,
            RiskAcknowledgment::query()->whereIn('assessment_id', $signed->pluck('id')->all())->get(['assessment_id'])->map(fn (RiskAcknowledgment $acknowledgment): string => $acknowledgment->assessment_id)->values()->all(),
            MedicalReferral::query()->where('work_center_id', $workCenterId)->get(['employee_id'])->map(fn (MedicalReferral $referral): string => $referral->employee_id)->unique()->values()->all(),
            PreventiveMeasure::query()->where('work_center_id', $workCenterId)->get(['assessment_id', 'position_id', 'status', 'due_on']),
            PauseAgreement::query()->where('work_center_id', $workCenterId)->get(['position_id'])->map(fn (PauseAgreement $agreement): string => $agreement->position_id)->values()->all(),
            InternalRegulation::query()->where('work_center_id', $workCenterId)->first(['id', 'parts', 'status']),
            SeatingDocument::query()->where('work_center_id', $workCenterId)->where('kind', DocumentKind::DiagnosisProgram)->exists(),
            $committee->userIdsOf($workCenterId) !== [],
        );
    }
}
