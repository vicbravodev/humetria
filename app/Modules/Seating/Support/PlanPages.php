<?php

declare(strict_types=1);

namespace App\Modules\Seating\Support;

use App\Modules\Instruments\Data\SeatPlacement;
use App\Modules\Seating\Enums\MeasureKind;
use App\Modules\Seating\Enums\MeasureStatus;
use App\Modules\Seating\Enums\RowState;
use App\Modules\Seating\Models\PauseAgreement;
use App\Modules\Seating\Models\PreventiveMeasure;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Tenancy\Contracts\Members;
use App\Modules\Tenancy\Data\MemberSummary;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

/**
 * What the action plan page sends to the browser: the measures and the pause agreements of a center, a page at a time each,
 * with whether R7 is met and the positions still without an agreement on pauses. No row names a person but the one who
 * answers for a measure.
 */
final readonly class PlanPages
{
    private const int PER_PAGE = 15;

    public function __construct(private Members $members, private ComplianceRows $rows) {}

    /**
     * @return array<string, mixed>
     */
    public function index(mixed $tenant, WorkCenterSummary $center, ?string $editing): array
    {
        $manage = Gate::allows('manage-seating');
        $people = $manage ? $this->members->all() : [];
        $names = $this->namesOf($center->id);
        $rule = new ComplianceRow('r7', RowState::Met);

        foreach ($this->rows->for($center->id) as $row) {
            $rule = $row->key === 'r7' ? $row : $rule;
        }

        return [
            'workCenter' => ['id' => $center->id, 'name' => $center->name],
            // Board LS-Plan: whether each assessment of a medium or high level has its measure (R7).
            'measuresRule' => ['state' => $rule->state->value, 'count' => $rule->count],
            'withoutAgreement' => $this->withoutAgreement($center->id),
            'measures' => $this->measures($tenant, $center->id, $names),
            'agreements' => $this->agreements($tenant, $center->id, $names),
            'editing' => $manage && $editing !== null ? $this->editing($editing, $center->id) : null,
            'canManage' => $manage,
            'kinds' => array_map(fn (MeasureKind $kind): array => ['value' => $kind->value, 'label' => $kind->label()], MeasureKind::cases()),
            'statuses' => array_map(fn (MeasureStatus $status): array => ['value' => $status->value, 'label' => $status->label()], MeasureStatus::cases()),
            'owners' => array_map(fn (MemberSummary $member): array => ['value' => (string) $member->userId, 'label' => $member->name], $people),
        ];
    }

    /**
     * The positions whose seat goes apart from the workstation (result B or C) with no agreement on pauses yet (R11), by name.
     *
     * @return list<string>
     */
    private function withoutAgreement(string $workCenterId): array
    {
        return array_values(StandingPosition::query()->where('work_center_id', $workCenterId)
            ->whereIn('placement', [SeatPlacement::Nearby, SeatPlacement::WithinCenter])
            ->where(fn ($query) => $query->whereNull('nature_allows')->orWhere('nature_allows', true))
            ->whereNotIn('id', PauseAgreement::query()->where('work_center_id', $workCenterId)->select('position_id'))
            ->orderBy('name')->get(['name'])->map(fn (StandingPosition $position): string => $position->name)->all());
    }

    /**
     * @return array<string, string> position names by id
     */
    private function namesOf(string $workCenterId): array
    {
        $names = [];

        foreach (StandingPosition::query()->where('work_center_id', $workCenterId)->get(['id', 'name']) as $position) {
            $names[$position->id] = $position->name;
        }

        return $names;
    }

    /**
     * @param  array<string, string>  $positions
     * @return LengthAwarePaginator<int, array{id: string, kindLabel: string, note: string|null, status: string, statusLabel: string, positionName: string|null, ownerName: string|null, dueOn: string|null, overdue: bool, hasEvidence: bool, fromAssessment: bool, evidenceHref: string|null}>
     */
    private function measures(mixed $tenant, string $workCenterId, array $positions): LengthAwarePaginator
    {
        $owners = [];

        foreach ($this->members->all() as $member) {
            $owners[$member->userId] = $member->name;
        }

        $today = Today::date();
        $page = PreventiveMeasure::query()->where('work_center_id', $workCenterId)->orderBy('created_at')->orderBy('id')->paginate(self::PER_PAGE, pageName: 'measures')->withQueryString();

        return $page->through(fn (PreventiveMeasure $measure): array => [
            'id' => $measure->id,
            'kindLabel' => $measure->kind->label(),
            'note' => $measure->note,
            'status' => $measure->status->value,
            'statusLabel' => $measure->status->label(),
            'positionName' => $measure->position_id === null ? null : ($positions[$measure->position_id] ?? null),
            'ownerName' => $measure->owner_user_id === null ? null : ($owners[$measure->owner_user_id] ?? null),
            'dueOn' => $measure->due_on?->toDateString(),
            'overdue' => $measure->due_on !== null && $measure->due_on->toDateString() < $today && in_array($measure->status, [MeasureStatus::Planned, MeasureStatus::InProgress], true),
            'hasEvidence' => $measure->evidence_path !== null,
            'fromAssessment' => $measure->assessment_id !== null,
            'evidenceHref' => $measure->evidence_path === null ? null : route('tenant.seating.measures.evidence', [$tenant, $measure->id]),
        ]);
    }

    /**
     * @param  array<string, string>  $positions
     * @return LengthAwarePaginator<int, array{id: string, positionName: string|null, pausesPerShift: int, minutesEach: int, agreedOn: string, howAgreed: string, evidenceHref: string|null}>
     */
    private function agreements(mixed $tenant, string $workCenterId, array $positions): LengthAwarePaginator
    {
        $page = PauseAgreement::query()->where('work_center_id', $workCenterId)->orderByDesc('agreed_on')->orderByDesc('id')->paginate(self::PER_PAGE, pageName: 'agreements')->withQueryString();

        return $page->through(fn (PauseAgreement $agreement): array => [
            'id' => $agreement->id,
            'positionName' => $positions[$agreement->position_id] ?? null,
            'pausesPerShift' => $agreement->pauses_per_shift,
            'minutesEach' => $agreement->minutes_each,
            'agreedOn' => $agreement->agreed_on->toDateString(),
            'howAgreed' => $agreement->how_agreed,
            'evidenceHref' => $agreement->evidence_path === null ? null : route('tenant.seating.pause-agreements.evidence', [$tenant, $agreement->id]),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function editing(string $id, string $workCenterId): ?array
    {
        $measure = PreventiveMeasure::query()->where('work_center_id', $workCenterId)->find($id);

        return $measure === null ? null : [
            'id' => $measure->id,
            'kindLabel' => $measure->kind->label(),
            'status' => $measure->status->value,
            'ownerUserId' => $measure->owner_user_id === null ? null : (string) $measure->owner_user_id,
            'dueOn' => $measure->due_on?->toDateString(),
            'hasEvidence' => $measure->evidence_path !== null,
            'cancelled' => $measure->status === MeasureStatus::Cancelled,
        ];
    }
}
