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
 * positions, people, seats or centers there are.
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
        return self::ofMany([$workCenterId], $committee)[$workCenterId];
    }

    /**
     * The same for several centers, in the same fixed number of queries whatever their number: each kind of record is read
     * once for all of them and split by center.
     *
     * @param  list<string>  $workCenterIds
     * @return array<string, self> by work center id, in the order given
     */
    public static function ofMany(array $workCenterIds, Committee $committee): array
    {
        $positions = StandingPosition::query()->whereIn('work_center_id', $workCenterIds)->get(['id', 'work_center_id', 'nature_allows', 'placement', 'max_people_at_once']);
        $signed = RiskAssessment::query()->whereIn('work_center_id', $workCenterIds)->where('status', AssessmentStatus::Signed)->orderBy('signed_at')->orderBy('id')
            ->get(['id', 'work_center_id', 'employee_id', 'position_id', 'level', 'scores', 'signed_at', 'reassessment_due', 'recorded_in_document_id']);
        $profiles = SeatingProfile::query()->whereIn('work_center_id', $workCenterIds)->get(['id', 'work_center_id', 'no_standing_positions', 'declared_on']);
        $assignments = PositionAssignment::query()->whereIn('position_id', $positions->modelKeys())->whereNull('ends_on')->get(['employee_id', 'position_id', 'starts_on']);
        $seats = Seat::query()->whereIn('work_center_id', $workCenterIds)->get(['work_center_id', 'position_id', 'use', 'quantity', 'condition', 'signposted']);
        $acknowledgments = RiskAcknowledgment::query()->whereIn('assessment_id', $signed->modelKeys())->get(['assessment_id']);
        $referrals = MedicalReferral::query()->whereIn('work_center_id', $workCenterIds)->get(['work_center_id', 'employee_id']);
        $measures = PreventiveMeasure::query()->whereIn('work_center_id', $workCenterIds)->get(['work_center_id', 'assessment_id', 'position_id', 'status', 'due_on']);
        $agreements = PauseAgreement::query()->whereIn('work_center_id', $workCenterIds)->get(['work_center_id', 'position_id']);
        $regulations = InternalRegulation::query()->whereIn('work_center_id', $workCenterIds)->get(['id', 'work_center_id', 'parts', 'status']);
        $diagnosed = SeatingDocument::query()->whereIn('work_center_id', $workCenterIds)->where('kind', DocumentKind::DiagnosisProgram)->get(['work_center_id']);
        $seated = $committee->centersWithMembers($workCenterIds);
        $facts = [];

        foreach ($workCenterIds as $id) {
            $here = $positions->where('work_center_id', $id)->values();
            $signedHere = $signed->where('work_center_id', $id)->values();
            $facts[$id] = new self(
                $profiles->firstWhere('work_center_id', $id),
                $here,
                $assignments->filter(fn (PositionAssignment $assignment): bool => $here->contains('id', $assignment->position_id))->values(),
                $seats->where('work_center_id', $id)->values(),
                $signedHere,
                array_values($acknowledgments->filter(fn (RiskAcknowledgment $acknowledgment): bool => $signedHere->contains('id', $acknowledgment->assessment_id))->map(fn (RiskAcknowledgment $acknowledgment): string => $acknowledgment->assessment_id)->all()),
                array_values(array_unique($referrals->where('work_center_id', $id)->map(fn (MedicalReferral $referral): string => $referral->employee_id)->all())),
                $measures->where('work_center_id', $id)->values(),
                array_values($agreements->where('work_center_id', $id)->map(fn (PauseAgreement $agreement): string => $agreement->position_id)->all()),
                $regulations->firstWhere('work_center_id', $id),
                $diagnosed->contains('work_center_id', $id),
                in_array($id, $seated, true),
            );
        }

        return $facts;
    }
}
