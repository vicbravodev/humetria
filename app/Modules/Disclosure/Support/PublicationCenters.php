<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support;

use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Illuminate\Validation\ValidationException;

/**
 * The work centers a text is published to: the ones RH named, which must be in use, or all of those in use.
 */
final readonly class PublicationCenters
{
    public function __construct(private WorkCenters $workCenters) {}

    /**
     * @param  list<string>|null  $named  Work centers in use; null means every one in use.
     * @return list<string> sorted
     *
     * @throws ValidationException when none is named, or one is not in use.
     */
    public function resolve(?array $named): array
    {
        $inUse = array_map(fn (WorkCenterSummary $center): string => $center->id, array_values(array_filter($this->workCenters->all(), fn (WorkCenterSummary $center): bool => ! $center->archived)));
        $centers = $named === null ? $inUse : array_values(array_unique($named));

        if ($centers === [] || array_diff($centers, $inUse) !== []) {
            throw ValidationException::withMessages(['work_center_ids' => 'Elige al menos un centro de trabajo de la empresa.']);
        }

        sort($centers);

        return $centers;
    }
}
