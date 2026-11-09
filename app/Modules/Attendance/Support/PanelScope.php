<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Illuminate\Support\Str;

/**
 * Whom a list of RH's panel is about: the people active in the company's centers in use, or in one of them, and the
 * name of each center. Two queries, whatever the number of people.
 */
final readonly class PanelScope
{
    /**
     * @param  array<string, string>  $names  the name of each center in use, by id
     * @param  list<string>  $people  the ids of the people in scope
     * @param  array<string, list<string>>  $byCenter  the ids of the active people of each center in use, by center id
     */
    private function __construct(public array $names, public array $people, public array $byCenter) {}

    public static function of(WorkCenters $workCenters, Roster $roster, ?WorkCenterSummary $center): self
    {
        $names = [];
        foreach ($workCenters->all() as $summary) {
            if (! $summary->archived) {
                $names[$summary->id] = $summary->name;
            }
        }
        $byCenter = $names === [] ? [] : $roster->activeIdsInMany(array_keys($names));
        $scope = $center === null ? array_keys($names) : [$center->id];

        return new self($names, array_merge([], ...array_values(array_intersect_key($byCenter, array_flip($scope)))), $byCenter);
    }

    /** The center a list is narrowed to: none when not asked; a 404 for one not in use of the company, or not an id. */
    public static function center(WorkCenters $workCenters, string $id): ?WorkCenterSummary
    {
        if ($id === '') {
            return null;
        }

        return (Str::isUuid($id) ? $workCenters->findActive($id) : null) ?? abort(404);
    }
}
