<?php

declare(strict_types=1);

namespace App\Modules\Demos\Actions;

use App\Modules\Demos\Enums\DemoStatus;
use App\Modules\Demos\Models\DemoRequest;
use Illuminate\Database\Query\Expression;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListDemoRequests
{
    /**
     * The requests, newest first, a page at a time; one status only when it is given, and only those that match what was
     * searched: the contact, the email, the company or the WhatsApp.
     *
     * @return LengthAwarePaginator<int, DemoRequest>
     */
    public function handle(?DemoStatus $status = null, string $search = ''): LengthAwarePaginator
    {
        return DemoRequest::query()
            ->with('owner:id,name')
            ->when($status !== null, fn ($query) => $query->where('status', $status?->value))
            ->matching(['name', 'email', 'company'], $search, ['whatsapp'])
            ->latest()
            ->paginate(25)
            ->withQueryString();
    }

    /**
     * The newest requests of each open stage of the funnel, a few per stage, in one query: the rest are in the list.
     *
     * @return array<string, list<DemoRequest>> by status, in the funnel's order
     */
    public function board(int $perStage = 5): array
    {
        $open = [DemoStatus::New, DemoStatus::Contacted, DemoStatus::Scheduled, DemoStatus::Trial];
        $board = array_fill_keys(array_map(fn (DemoStatus $status): string => $status->value, $open), []);

        $ranked = DemoRequest::query()
            ->select('*')
            ->selectRaw('row_number() over (partition by status order by created_at desc, id) as position')
            ->whereIn('status', array_keys($board));

        $cards = DemoRequest::query()->fromSub($ranked, 'demo_requests')
            ->where(new Expression('position'), '<=', $perStage)
            ->with('owner:id,name')
            ->orderByDesc('created_at')
            ->orderBy('id')
            ->get();

        foreach ($cards as $card) {
            $board[$card->status->value][] = $card;
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
