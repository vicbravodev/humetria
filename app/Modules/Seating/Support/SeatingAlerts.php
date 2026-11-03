<?php

declare(strict_types=1);

namespace App\Modules\Seating\Support;

use App\Modules\Instruments\Data\SeatPlacement;
use App\Modules\Seating\Enums\AssessmentStatus;
use App\Modules\Seating\Enums\RegulationStatus;
use App\Modules\Seating\Enums\ReportStatus;
use App\Modules\Seating\Enums\SeatCondition;
use App\Modules\Seating\Enums\SeatUse;
use App\Modules\Seating\Models\InternalRegulation;
use App\Modules\Seating\Models\PauseAgreement;
use App\Modules\Seating\Models\PositionAssignment;
use App\Modules\Seating\Models\RiskAssessment;
use App\Modules\Seating\Models\Seat;
use App\Modules\Seating\Models\SeatReport;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Telework\Contracts\Committee;

/**
 * What needs attention in a work center, as counts: people on their feet never assessed, assessments due again, seat reports
 * open for too long, positions whose seat goes apart from the workstation with no pause agreement, a regulation that lacks a
 * part or its deposit, seats away from the workstation not signposted, and a Comisión with nobody on it. Counts only: the
 * digest and the board say how many, never who.
 */
final readonly class SeatingAlerts
{
    public function __construct(private Committee $committee) {}

    /**
     * @return array{unassessed: int, reassessment: int, reports: int, pauses: int, regulation: int, signage: int, committee: int}
     */
    public function for(string $workCenterId): array
    {
        return [...$this->people($workCenterId), ...[
            'reports' => SeatReport::query()->where('work_center_id', $workCenterId)->where('status', ReportStatus::Open)->where('created_at', '<', now()->subDays(config()->integer('seating.report_alert_days')))->count(),
            'pauses' => StandingPosition::query()->where('work_center_id', $workCenterId)->whereIn('placement', [SeatPlacement::Nearby, SeatPlacement::WithinCenter])->whereNotIn('id', PauseAgreement::query()->select('position_id'))->count(),
            'regulation' => $this->regulationLacks($workCenterId) ? 1 : 0,
            'signage' => Seat::query()->where('work_center_id', $workCenterId)->where('condition', '!=', SeatCondition::OutOfService)->where('signposted', false)
                ->where(fn ($away) => $away->where('use', SeatUse::RestArea)->orWhereNull('position_id'))->count(),
            'committee' => $this->committee->userIdsOf($workCenterId) === [] ? 1 : 0,
        ]];
    }

    /**
     * @return array{unassessed: int, reassessment: int}
     */
    private function people(string $workCenterId): array
    {
        $eligible = array_values(StandingPosition::query()->where('work_center_id', $workCenterId)->where(fn ($query) => $query->whereNull('nature_allows')->orWhere('nature_allows', true))->get(['id'])->map(fn (StandingPosition $position): string => $position->id)->all());
        $people = array_values(PositionAssignment::query()->whereIn('position_id', $eligible)->whereNull('ends_on')->get(['employee_id'])->map(fn (PositionAssignment $assignment): string => $assignment->employee_id)->all());
        $current = [];

        foreach (RiskAssessment::query()->whereIn('employee_id', $people)->where('status', AssessmentStatus::Signed)->orderBy('signed_at')->get(['employee_id', 'signed_at', 'reassessment_due']) as $assessment) {
            $current[$assessment->employee_id] = $assessment;
        }

        return [
            'unassessed' => count(array_diff($people, array_keys($current))),
            'reassessment' => count(array_filter($current, fn (RiskAssessment $assessment): bool => $assessment->reassessment_due || ($assessment->signed_at !== null && AssessmentTerm::overdue($assessment->signed_at)))),
        ];
    }

    private function regulationLacks(string $workCenterId): bool
    {
        $regulation = InternalRegulation::query()->where('work_center_id', $workCenterId)->first();

        return $regulation === null
            || count(array_filter($regulation->parts, fn (string $part): bool => trim($part) !== '')) < 4
            || ! in_array($regulation->status, [RegulationStatus::Deposited, RegulationStatus::Distributed], true);
    }
}
