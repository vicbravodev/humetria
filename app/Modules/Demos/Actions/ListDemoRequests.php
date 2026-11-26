<?php

declare(strict_types=1);

namespace App\Modules\Demos\Actions;

use App\Modules\Demos\Enums\DemoOrigin;
use App\Modules\Demos\Enums\DemoStatus;
use App\Modules\Demos\Models\DemoRequest;
use App\Modules\Demos\Support\DemoListFilters;
use Illuminate\Database\Query\Expression;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListDemoRequests
{
    /**
     * The requests, newest first, a page at a time, each with its origin; one status only when it is given, and only those
     * the filters leave: what was searched (the contact, the email, the company or the WhatsApp), origin, owner and size.
     *
     * @return LengthAwarePaginator<int, DemoRequest>
     */
    public function handle(?DemoStatus $status = null, ?DemoListFilters $filters = null): LengthAwarePaginator
    {
        return ($filters ?? DemoListFilters::from())->apply(DemoRequest::query())
            ->select('*')
            ->selectRaw(DemoOrigin::SQL.' as origin')
            ->with('owner:id,name')
            ->when($status !== null, fn ($query) => $query->where('status', $status?->value))
            ->latest()
            ->paginate(25)
            ->withQueryString();
    }

    /**
     * The newest requests of each open stage of the funnel that the filters leave, a few per stage, and how many each
     * stage holds after them, in one query: the rest are in the list.
     *
     * @return array<string, array{total: int, cards: list<DemoRequest>}> by status, in the funnel's order
     */
    public function board(?DemoListFilters $filters = null, int $perStage = 5): array
    {
        $open = array_map(fn (DemoStatus $status): string => $status->value, [DemoStatus::New, DemoStatus::Contacted, DemoStatus::Scheduled, DemoStatus::Trial]);

        $ranked = ($filters ?? DemoListFilters::from())->apply(DemoRequest::query())
            ->select('*')
            ->selectRaw(DemoOrigin::SQL.' as origin')
            ->selectRaw('row_number() over (partition by status order by created_at desc, id) as position')
            ->selectRaw('count(*) over (partition by status) as stage_total')
            ->whereIn('status', $open);

        $cards = DemoRequest::query()->fromSub($ranked, 'demo_requests')
            ->where(new Expression('position'), '<=', $perStage)
            ->with('owner:id,name')
            ->orderByDesc('created_at')
            ->orderBy('id')
            ->get();

        $board = [];

        foreach ($open as $status) {
            $stage = array_values($cards->filter(fn (DemoRequest $card): bool => $card->status->value === $status)->all());
            $total = $stage === [] ? 0 : $stage[0]->getAttribute('stage_total');
            $board[$status] = ['total' => is_numeric($total) ? (int) $total : 0, 'cards' => $stage];
        }

        return $board;
    }

    /**
     * How many requests each status holds, and all of them, in one query.
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        $counts = ['all' => 0, ...array_fill_keys(array_map(fn (DemoStatus $status): string => $status->value, DemoStatus::cases()), 0)];

        foreach (DemoRequest::query()->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->get() as $row) {
            if (is_string($row->status) && is_numeric($row->total)) {
                $counts[$row->status] = (int) $row->total;
            }
        }

        $counts['all'] = array_sum($counts);

        return $counts;
    }
}
