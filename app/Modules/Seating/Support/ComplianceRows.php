<?php

declare(strict_types=1);

namespace App\Modules\Seating\Support;

use App\Modules\Instruments\Data\RiskLevel;
use App\Modules\Instruments\Data\SeatPlacement;
use App\Modules\Seating\Enums\MeasureStatus;
use App\Modules\Seating\Enums\RegulationStatus;
use App\Modules\Seating\Enums\RowState;
use App\Modules\Seating\Enums\SeatCondition;
use App\Modules\Seating\Enums\SeatUse;
use App\Modules\Seating\Models\PositionAssignment;
use App\Modules\Seating\Models\PreventiveMeasure;
use App\Modules\Seating\Models\RiskAssessment;
use App\Modules\Seating\Models\Seat;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Telework\Contracts\Committee;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The thirteen requirements of a work center and where each stands: the rules of the table «Qué cuenta como cumplido» of
 * the design, one by one. They are counts of what was registered, never an «apto». A position whose nature does not allow
 * sitting (industrial, with its reason) is left out of R2, R4 and R5 and counts as documented in R1.
 */
final readonly class ComplianceRows
{
    public function __construct(private Committee $committee) {}

    /**
     * @return list<ComplianceRow>
     */
    public function for(string $workCenterId): array
    {
        $facts = ComplianceFacts::of($workCenterId, $this->committee);
        $eligible = $facts->positions->filter(fn (StandingPosition $position): bool => $position->nature_allows !== false);
        $current = $this->currentOfPeople($facts, $eligible);

        return [
            $this->r1($facts),
            $this->r2($facts, $eligible, $current),
            $this->r3($facts),
            $this->r4($facts, $eligible),
            $this->r5($facts, $eligible),
            $this->r6($facts),
            $this->r7($facts, $current),
            $this->r8($facts, $current),
            $this->r9($facts, $current),
            $this->r10($facts),
            $this->r11($facts, $eligible),
            $this->row('r12', $facts->hasDiagnosis ? 0 : 1),
            $this->row('r13', $facts->hasCommittee ? 0 : 1),
        ];
    }

    private function r1(ComplianceFacts $facts): ComplianceRow
    {
        if ($facts->profile === null) {
            return $this->row('r1', 1);
        }

        if ($facts->positions->isEmpty()) {
            return $this->row('r1', $facts->profile->no_standing_positions ? 0 : 1);
        }

        $held = $facts->assignments->pluck('position_id')->all();
        $empty = $facts->positions->filter(fn (StandingPosition $position): bool => $position->nature_allows !== false && ! in_array($position->id, $held, true));

        return $this->row('r1', 0, $empty->count());
    }

    /**
     * @param  Collection<int, StandingPosition>  $eligible
     * @param  array<string, RiskAssessment>  $current  by employee id
     */
    private function r2(ComplianceFacts $facts, Collection $eligible, array $current): ComplianceRow
    {
        $unassessed = $facts->assignments->filter(fn (PositionAssignment $assignment): bool => $eligible->contains('id', $assignment->position_id) && ! array_key_exists($assignment->employee_id, $current));
        $due = array_filter($current, fn (RiskAssessment $assessment): bool => $assessment->reassessment_due || ($assessment->signed_at !== null && AssessmentTerm::overdue($assessment->signed_at)));

        return $this->row('r2', $unassessed->unique('employee_id')->count(), count($due), $this->oldest($unassessed->isNotEmpty() ? $unassessed->map(fn (PositionAssignment $assignment): CarbonImmutable => $assignment->starts_on)->all() : array_map(fn (RiskAssessment $assessment): ?CarbonImmutable => $assessment->signed_at, $due)));
    }

    private function r3(ComplianceFacts $facts): ComplianceRow
    {
        $without = $facts->signed->filter(fn (RiskAssessment $assessment): bool => $assessment->recorded_in_document_id === null);

        return $this->row('r3', $without->count(), since: $this->oldest($without->map(fn (RiskAssessment $assessment): ?CarbonImmutable => $assessment->signed_at)->all()));
    }

    /**
     * @param  Collection<int, StandingPosition>  $eligible
     */
    private function r4(ComplianceFacts $facts, Collection $eligible): ComplianceRow
    {
        $usable = $this->usableSeats($facts);
        $none = $eligible->filter(fn (StandingPosition $position): bool => ($usable[$position->id] ?? 0) === 0);
        $repair = $facts->seats->filter(fn (Seat $seat): bool => $seat->condition === SeatCondition::NeedsRepair)->pluck('position_id')->all();
        $warn = $eligible->filter(fn (StandingPosition $position): bool => ($usable[$position->id] ?? 0) < $position->max_people_at_once || in_array($position->id, $repair, true));

        return $this->row('r4', $none->count(), $warn->count());
    }

    /**
     * @param  Collection<int, StandingPosition>  $eligible
     */
    private function r5(ComplianceFacts $facts, Collection $eligible): ComplianceRow
    {
        $rests = $facts->seats->filter(fn (Seat $seat): bool => $seat->use === SeatUse::RestArea && $seat->condition !== SeatCondition::OutOfService);
        $without = $this->awayFromWorkstation($eligible)->filter(fn (StandingPosition $position): bool => ! $rests->contains(fn (Seat $seat): bool => $seat->position_id === null || $seat->position_id === $position->id));

        return $this->row('r5', $without->count());
    }

    private function r6(ComplianceFacts $facts): ComplianceRow
    {
        $unmarked = $facts->seats->filter(fn (Seat $seat): bool => $seat->condition !== SeatCondition::OutOfService && ! $seat->signposted && ($seat->use === SeatUse::RestArea || $seat->position_id === null));

        return $this->row('r6', $unmarked->count());
    }

    /**
     * @param  array<string, RiskAssessment>  $current
     */
    private function r7(ComplianceFacts $facts, array $current): ComplianceRow
    {
        $kept = $facts->measures->filter(fn (PreventiveMeasure $measure): bool => $measure->status !== MeasureStatus::Cancelled);
        $needing = array_filter($current, fn (RiskAssessment $assessment): bool => in_array($assessment->level, [RiskLevel::Medium, RiskLevel::High], true));
        $without = array_filter($needing, fn (RiskAssessment $assessment): bool => ! $kept->contains(fn (PreventiveMeasure $measure): bool => $measure->assessment_id === $assessment->id || ($measure->position_id !== null && $measure->position_id === $assessment->position_id)));
        $today = Today::date();
        $overdue = $kept->filter(fn (PreventiveMeasure $measure): bool => in_array($measure->status, [MeasureStatus::Planned, MeasureStatus::InProgress], true) && $measure->due_on !== null && $measure->due_on->toDateString() < $today);

        return $this->row(
            'r7',
            count($without),
            $overdue->count(),
            $without !== [] ? $this->oldest(array_map(fn (RiskAssessment $assessment): ?CarbonImmutable => $assessment->signed_at, $without)) : $this->oldest($overdue->map(fn (PreventiveMeasure $measure): ?CarbonImmutable => $measure->due_on)->all()),
        );
    }

    /**
     * @param  array<string, RiskAssessment>  $current
     */
    private function r8(ComplianceFacts $facts, array $current): ComplianceRow
    {
        $unconfirmed = array_filter($current, fn (RiskAssessment $assessment): bool => ! in_array($assessment->id, $facts->acknowledged, true));

        return $this->row('r8', count($unconfirmed), since: $this->oldest(array_map(fn (RiskAssessment $assessment): ?CarbonImmutable => $assessment->signed_at, $unconfirmed)));
    }

    /**
     * @param  array<string, RiskAssessment>  $current
     */
    private function r9(ComplianceFacts $facts, array $current): ComplianceRow
    {
        $unattended = array_filter($current, fn (RiskAssessment $assessment): bool => ($assessment->scores['discomfort'] ?? 0) === 3 && ! in_array($assessment->employee_id, $facts->referred, true));

        return $this->row('r9', count($unattended), since: $this->oldest(array_map(fn (RiskAssessment $assessment): ?CarbonImmutable => $assessment->signed_at, $unattended)));
    }

    private function r10(ComplianceFacts $facts): ComplianceRow
    {
        $regulation = $facts->regulation;

        if ($regulation === null || count(array_filter($regulation->parts, fn (string $part): bool => trim($part) !== '')) < 4 || $regulation->status === RegulationStatus::Draft) {
            return $this->row('r10', 1);
        }

        return $this->row('r10', 0, $regulation->status === RegulationStatus::Distributed ? 0 : 1);
    }

    /**
     * @param  Collection<int, StandingPosition>  $eligible
     */
    private function r11(ComplianceFacts $facts, Collection $eligible): ComplianceRow
    {
        return $this->row('r11', $this->awayFromWorkstation($eligible)->filter(fn (StandingPosition $position): bool => ! in_array($position->id, $facts->agreed, true))->count());
    }

    /**
     * The positions whose seat goes apart from the workstation (result B or C): they need a rest seat and a pause agreement.
     *
     * @param  Collection<int, StandingPosition>  $eligible
     * @return Collection<int, StandingPosition>
     */
    private function awayFromWorkstation(Collection $eligible): Collection
    {
        return $eligible->filter(fn (StandingPosition $position): bool => in_array($position->placement, [SeatPlacement::Nearby, SeatPlacement::WithinCenter], true));
    }

    /**
     * The seats in use of each position: those out of service do not count.
     *
     * @return array<string, int>
     */
    private function usableSeats(ComplianceFacts $facts): array
    {
        $usable = [];

        foreach ($facts->seats as $seat) {
            if ($seat->position_id !== null && $seat->condition !== SeatCondition::OutOfService) {
                $usable[$seat->position_id] = ($usable[$seat->position_id] ?? 0) + $seat->quantity;
            }
        }

        return $usable;
    }

    /**
     * The latest signed assessment of each person who holds an eligible position.
     *
     * @param  Collection<int, StandingPosition>  $eligible
     * @return array<string, RiskAssessment> by employee id
     */
    private function currentOfPeople(ComplianceFacts $facts, Collection $eligible): array
    {
        $people = $facts->assignments->filter(fn (PositionAssignment $assignment): bool => $eligible->contains('id', $assignment->position_id))->map(fn (PositionAssignment $assignment): string => $assignment->employee_id)->all();
        $current = [];

        foreach ($facts->signed as $assessment) {
            if (in_array($assessment->employee_id, $people, true)) {
                $current[$assessment->employee_id] = $assessment;
            }
        }

        return $current;
    }

    /**
     * A row is missing while `$missing` is above none, with a warning while `$warning` is, and met otherwise; its count is
     * what is behind its state.
     */
    private function row(string $key, int $missing, int $warning = 0, ?CarbonImmutable $since = null): ComplianceRow
    {
        return match (true) {
            $missing > 0 => new ComplianceRow($key, RowState::Missing, $missing, $since),
            $warning > 0 => new ComplianceRow($key, RowState::Warning, $warning, $since),
            default => new ComplianceRow($key, RowState::Met),
        };
    }

    /**
     * @param  array<array-key, CarbonImmutable|null>  $dates
     */
    private function oldest(array $dates): ?CarbonImmutable
    {
        $known = array_values(array_filter($dates, fn (?CarbonImmutable $date): bool => $date !== null));

        return $known === [] ? null : min($known);
    }
}
