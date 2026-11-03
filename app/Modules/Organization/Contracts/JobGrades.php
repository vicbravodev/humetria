<?php

declare(strict_types=1);

namespace App\Modules\Organization\Contracts;

use App\Modules\Organization\Data\JobGradeData;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The job grades (category and level) of the current company, as other modules read them.
 */
interface JobGrades
{
    /**
     * A page of the grades whose category or level has what was typed, ignoring case and accents, by category and level.
     *
     * @return LengthAwarePaginator<int, JobGradeData>
     */
    public function page(string $search, int $perPage): LengthAwarePaginator;

    /** A grade by id; null outside the company. */
    public function find(string $id): ?JobGradeData;

    /**
     * Several grades at once; ids outside the company are left out.
     *
     * @param  list<string>  $ids
     * @return array<string, JobGradeData> by id
     */
    public function findMany(array $ids): array;
}
