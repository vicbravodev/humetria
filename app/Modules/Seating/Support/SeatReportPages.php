<?php

declare(strict_types=1);

namespace App\Modules\Seating\Support;

use App\Modules\Seating\Enums\ReportStatus;
use App\Modules\Seating\Models\SeatReport;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

/**
 * What the seat reports page sends to the browser: the open (or resolved) reports of a center, a page at a time, and how
 * many there are of each. No report names a person.
 */
final readonly class SeatReportPages
{
    private const int REPORTS_PER_PAGE = 15;

    /**
     * @return array<string, mixed>
     */
    public function index(WorkCenterSummary $center, string $status): array
    {
        $state = $status === 'resolved' ? ReportStatus::Resolved : ReportStatus::Open;

        return [
            'workCenter' => ['id' => $center->id, 'name' => $center->name],
            'reports' => $this->reports($center->id, $state),
            'status' => $state->value,
            'openCount' => SeatReport::query()->where('work_center_id', $center->id)->where('status', ReportStatus::Open)->count(),
            'resolvedCount' => SeatReport::query()->where('work_center_id', $center->id)->where('status', ReportStatus::Resolved)->count(),
            'canResolve' => Gate::allows('manage-seating'),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, array{id: string, description: string, positionName: string|null, reportedOn: string, resolution: string|null, resolvedOn: string|null}>
     */
    private function reports(string $workCenterId, ReportStatus $state): LengthAwarePaginator
    {
        $page = SeatReport::query()->where('work_center_id', $workCenterId)->where('status', $state)->orderBy('created_at')->orderBy('id')->paginate(self::REPORTS_PER_PAGE)->withQueryString();
        $ids = array_values(array_filter(array_map(fn (SeatReport $report): ?string => $report->position_id, $page->items())));
        $names = [];

        foreach (StandingPosition::query()->whereIn('id', $ids)->get(['id', 'name']) as $position) {
            $names[$position->id] = $position->name;
        }

        $zone = config()->string('app.display_timezone');

        return $page->through(fn (SeatReport $report): array => [
            'id' => $report->id,
            'description' => $report->description,
            'positionName' => $report->position_id === null ? null : ($names[$report->position_id] ?? null),
            'reportedOn' => $report->created_at->setTimezone($zone)->toDateString(),
            'resolution' => $report->resolution,
            'resolvedOn' => $report->resolved_at?->setTimezone($zone)->toDateString(),
        ]);
    }
}
