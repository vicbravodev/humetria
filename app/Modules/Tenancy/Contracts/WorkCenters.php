<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Contracts;

use App\Modules\Tenancy\Data\WorkCenterSummary;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The current company's work centers, as other modules need them: to place people in one and to name it.
 */
interface WorkCenters
{
    /**
     * Every work center of the current company, by name, archived ones included.
     *
     * @return list<WorkCenterSummary>
     */
    public function all(): array;

    /**
     * The current company's work centers in use (not archived), by name, a page at a time.
     *
     * @return LengthAwarePaginator<int, WorkCenterSummary>
     */
    public function activePage(int $perPage = 25, string $pageName = 'page'): LengthAwarePaginator;

    /** A work center in use (not archived) by id; null when archived or outside the company. */
    public function findActive(string $id): ?WorkCenterSummary;
}
