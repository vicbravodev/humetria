<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Contracts;

use App\Modules\Tenancy\Data\WorkCenterProfile;
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

    /**
     * Like findActive(), holding the work center's row until the caller's transaction ends, so archiving it and
     * starting an evaluation of it never both pass their checks.
     */
    public function lockActive(string $id): ?WorkCenterSummary;

    /** A work center by id, archived ones included, as a report names it; null outside the company. */
    public function profile(string $id): ?WorkCenterProfile;

    /**
     * The 32 federal entities, for an address another module takes: label by ISO 3166-2:MX code.
     *
     * @return array<string, string>
     */
    public function states(): array;
}
