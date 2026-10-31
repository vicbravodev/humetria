<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support;

use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * The work centers of the company the signed-in user may see in NOM-037: owner, hr and viewer see every one in use; the
 * committee only those it sits on. Reusable by any NOM-037 page that filters or chooses a center.
 */
final readonly class VisibleCenters
{
    public const int LIMIT = 20;

    public function __construct(private CommitteeGate $gate, private WorkCenters $workCenters) {}

    /**
     * The only center ids the user may see, or null when every center of the company is visible.
     *
     * @return list<string>|null
     */
    public function limitedTo(int $userId): ?array
    {
        return Gate::allows('manage-telework') || ! Gate::allows('review-workplaces') ? null : $this->gate->workCenterIds($userId);
    }

    /**
     * Centers in use whose name has what was typed (accents and case ignored), by name.
     *
     * @return list<WorkCenterSummary>
     */
    public function search(int $userId, string $query): array
    {
        $limit = $this->limitedTo($userId);
        $needle = Str::lower(Str::ascii(trim($query)));

        return array_slice(array_values(array_filter($this->workCenters->all(), fn (WorkCenterSummary $center): bool => ! $center->archived
            && ($limit === null || in_array($center->id, $limit, true))
            && str_contains(Str::lower(Str::ascii($center->name)), $needle))), 0, self::LIMIT);
    }
}
