<?php

declare(strict_types=1);

namespace App\Modules\Demos\Actions;

use App\Modules\Demos\Enums\DemoStatus;
use App\Modules\Demos\Models\DemoRequest;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListDemoRequests
{
    /**
     * The requests, newest first, a page at a time; one status only when it is given.
     *
     * @return LengthAwarePaginator<int, DemoRequest>
     */
    public function handle(?DemoStatus $status = null): LengthAwarePaginator
    {
        return DemoRequest::query()
            ->when($status !== null, fn ($query) => $query->where('status', $status?->value))
            ->latest()
            ->paginate(25)
            ->withQueryString();
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
