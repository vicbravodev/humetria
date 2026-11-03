<?php

declare(strict_types=1);

namespace App\Modules\Seating\Support;

use App\Modules\Telework\Contracts\Committee;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

/**
 * The work centers of the company the signed-in user may see in Ley Silla: owner, hr and viewer see every one in use; the
 * Comisión only those it sits on.
 */
final readonly class VisibleCenters
{
    public function __construct(private Committee $committee, private WorkCenters $workCenters) {}

    /**
     * The only center ids the user may see, or null when every center of the company is visible.
     *
     * @return list<string>|null
     */
    public function limitedTo(int $userId): ?array
    {
        return Gate::allows('manage-seating') || ! Gate::allows('assess-seating') ? null : $this->committee->workCenterIds($userId);
    }

    /**
     * The centers in use the user may see, by name, a page at a time.
     *
     * @return LengthAwarePaginator<int, WorkCenterSummary>
     */
    public function page(int $userId, int $perPage): LengthAwarePaginator
    {
        $limit = $this->limitedTo($userId);
        // array_slice() below numbers what it takes anew.
        $visible = array_values(array_filter( // @pest-mutate-ignore: UnwrapArrayValues
            $this->workCenters->all(), fn (WorkCenterSummary $center): bool => ! $center->archived && ($limit === null || in_array($center->id, $limit, true))));
        $page = LengthAwarePaginator::resolveCurrentPage();

        return (new LengthAwarePaginator(array_slice($visible, ($page - 1) * $perPage, $perPage), count($visible), $perPage, $page, ['path' => LengthAwarePaginator::resolveCurrentPath()]))->withQueryString();
    }
}
