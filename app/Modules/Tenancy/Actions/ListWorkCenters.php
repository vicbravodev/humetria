<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListWorkCenters
{
    public const int PER_PAGE = 25;

    /**
     * The current company's work centers in use, or the archived ones, by name, a page at a time.
     *
     * @return LengthAwarePaginator<int, WorkCenter>
     */
    public function handle(bool $archived): LengthAwarePaginator
    {
        return WorkCenter::query()
            ->when($archived, fn ($query) => $query->whereNotNull('archived_at'), fn ($query) => $query->whereNull('archived_at'))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }
}
