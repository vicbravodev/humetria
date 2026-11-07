<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Enums\CorrectionStatus;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\CorrectionRequest;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterQuery;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

/**
 * What the queue of correction requests sends to the browser: the requests of one state, oldest first, 25 at a time,
 * searched by the person's name, with how many there are of each state. Each row: who (name and job title, from the
 * Roster), the time asked and the mark's current one in local time, the type, the reason, how many days it waits, and RH's
 * note once resolved. Never a position or a device.
 */
final readonly class CorrectionPages
{
    private const int PER_PAGE = 25;

    public function __construct(private Roster $roster) {}

    /**
     * @return array<string, mixed>
     */
    public function index(string $status, string $search): array
    {
        $state = CorrectionStatus::tryFrom($status) ?? CorrectionStatus::Pending;
        $counts = CorrectionRequest::query()->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            'status' => $state->value,
            'filters' => ['q' => $search],
            'counts' => array_combine(
                array_map(fn (CorrectionStatus $case): string => $case->value, CorrectionStatus::cases()),
                array_map(fn (CorrectionStatus $case): int => is_int($counts[$case->value] ?? null) ? $counts[$case->value] : 0, CorrectionStatus::cases()),
            ),
            'requests' => $this->requests($state, $search),
            'canResolve' => Gate::allows('manage-attendance'),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, array{id: string, person: array{name: string, jobTitle: string}, type: string, requestedAt: string, currentAt: string|null, reason: string, ageDays: int, status: string, note: string|null}>
     */
    private function requests(CorrectionStatus $state, string $search): LengthAwarePaginator
    {
        $page = CorrectionRequest::query()->where('status', $state)
            ->when($search !== '', fn (Builder $query) => $query->whereIn('employee_id', $this->roster->matchingIds(new RosterQuery(search: $search))))
            ->orderBy('created_at')->orderBy('id')
            ->paginate(self::PER_PAGE)->withQueryString();
        $people = $this->roster->findMany(array_values(array_unique(array_map(fn (CorrectionRequest $request): string => $request->employee_id, $page->items()))));
        $markIds = array_values(array_filter(array_map(fn (CorrectionRequest $request): ?string => $request->mark_id, $page->items())));
        $marks = AttendanceMark::query()->whereIn('id', $markIds)->get(['id', 'marked_at'])->keyBy('id');
        $zone = config()->string('app.display_timezone');
        $today = CarbonImmutable::now($zone)->startOfDay();

        return $page->through(fn (CorrectionRequest $request): array => [
            'id' => $request->id,
            'person' => ['name' => $people[$request->employee_id]->name ?? '—', 'jobTitle' => $people[$request->employee_id]->jobTitle ?? ''],
            'type' => $request->type->value,
            'requestedAt' => $request->proposed_at->setTimezone($zone)->format('Y-m-d H:i'),
            'currentAt' => $request->mark_id === null ? null : $marks->get($request->mark_id)?->marked_at?->setTimezone($zone)->format('Y-m-d H:i'),
            'reason' => $request->reason,
            'ageDays' => (int) $request->created_at->setTimezone($zone)->startOfDay()->diffInDays($today),
            'status' => $request->status->value,
            'note' => $request->note,
        ]);
    }
}
