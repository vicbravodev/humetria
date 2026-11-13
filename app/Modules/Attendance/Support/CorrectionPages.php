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
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * What the queue of correction requests sends to the browser: the requests of one state, oldest first, 25 at a time,
 * searched by the person's name, with how many there are of each state. Each row: who (name and job title, from the
 * Roster), the time asked and the mark's time before it (local), the type, the reason, how many days it waits, and RH's
 * note once resolved (the reason and the note only for whoever manages attendance: free text that may be medical). Never a position or a device.
 *
 * One of them is in view (`selectedId`, `detail`): the one asked for by id, of any state, or the first of the page. Its
 * detail is its row plus, while pending, what approving it would do (CorrectionPreview).
 */
final readonly class CorrectionPages
{
    private const int PER_PAGE = 25;

    public function __construct(private Roster $roster) {}

    /**
     * @return array<string, mixed>
     */
    public function index(string $status, string $search, string $requestId = ''): array
    {
        $state = CorrectionStatus::tryFrom($status) ?? CorrectionStatus::Pending;
        $counts = CorrectionRequest::query()->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $page = CorrectionRequest::query()->where('status', $state)
            ->when($search !== '', fn (Builder $query) => $query->whereIn('employee_id', $this->roster->matchingIds(new RosterQuery(search: $search))))
            ->orderBy('created_at')->orderBy('id')
            ->paginate(self::PER_PAGE)->appends(['status' => $state->value, 'q' => $search]);
        $selected = $this->selected($requestId, $page->items());
        $rows = $this->rows([...$page->items(), ...($selected === null ? [] : [$selected])]);

        return [
            'status' => $state->value,
            'filters' => ['q' => $search],
            'counts' => array_combine(
                array_map(fn (CorrectionStatus $case): string => $case->value, CorrectionStatus::cases()),
                array_map(fn (CorrectionStatus $case): int => is_int($counts[$case->value] ?? null) ? $counts[$case->value] : 0, CorrectionStatus::cases()),
            ),
            'requests' => $page->through(fn (CorrectionRequest $request): array => $rows[$request->id]),
            'selectedId' => $selected?->id,
            'detail' => $selected === null ? null : [...$rows[$selected->id], ...(CorrectionPreview::of($selected) ?? ['day' => null, 'effect' => null])],
            'canResolve' => Gate::allows('manage-attendance'),
        ];
    }

    /**
     * The request asked for, whatever its state (one of another company is not found: the first of the page instead), or
     * the first of the page.
     *
     * @param  array<int, CorrectionRequest>  $page
     */
    private function selected(string $requestId, array $page): ?CorrectionRequest
    {
        $asked = Str::isUuid($requestId) ? CorrectionRequest::query()->find($requestId) : null;

        return $asked ?? $page[0] ?? null;
    }

    /**
     * Each request as the browser reads it, by id: its person and the mark's time before it, in two queries and one for
     * the people whatever the number of requests.
     *
     * @param  list<CorrectionRequest>  $requests
     * @return array<string, array{id: string, person: array{name: string, jobTitle: string}, type: string, requestedAt: string, previousAt: string|null, reason: string|null, ageDays: int, status: string, note: string|null}>
     */
    private function rows(array $requests): array
    {
        $people = $this->roster->findMany(array_values(array_unique(array_map(fn (CorrectionRequest $request): string => $request->employee_id, $requests))));
        $markIds = array_values(array_filter(array_map(fn (CorrectionRequest $request): ?string => $request->mark_id, $requests)));
        $marks = AttendanceMark::query()->whereIn('id', $markIds)->get(['id', 'marked_at'])->keyBy('id');
        $zone = config()->string('app.display_timezone');
        $today = CarbonImmutable::now($zone)->startOfDay();
        // What a person wrote and what RH answered is free text, and may be medical: only whoever manages it reads it.
        $manage = Gate::allows('manage-attendance');
        $rows = [];

        foreach ($requests as $request) {
            $rows[$request->id] = [
                'id' => $request->id,
                'person' => ['name' => $people[$request->employee_id]->name ?? '—', 'jobTitle' => $people[$request->employee_id]->jobTitle ?? ''],
                'type' => $request->type->value,
                'requestedAt' => $request->proposed_at->setTimezone($zone)->format('Y-m-d H:i'),
                // The mark's time when it was asked: after an approval, the time before the correction.
                'previousAt' => $request->mark_id === null ? null : $marks->get($request->mark_id)?->marked_at?->setTimezone($zone)->format('Y-m-d H:i'),
                'reason' => $manage ? $request->reason : null,
                'ageDays' => (int) $request->created_at->setTimezone($zone)->startOfDay()->diffInDays($today),
                'status' => $request->status->value,
                'note' => $manage ? $request->note : null,
            ];
        }

        return $rows;
    }
}
