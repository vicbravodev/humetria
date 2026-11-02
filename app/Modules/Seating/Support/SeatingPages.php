<?php

declare(strict_types=1);

namespace App\Modules\Seating\Support;

use App\Modules\Seating\Enums\Sector;
use App\Modules\Seating\Models\SeatingProfile;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Illuminate\Support\Facades\Gate;

/**
 * What the Ley Silla pages send to the browser: the list of centers with their declared sector and the page of one
 * center. Nothing here names a person: the viewer sees the same.
 */
final readonly class SeatingPages
{
    private const int CENTERS_PER_PAGE = 12;

    public function __construct(private VisibleCenters $visible) {}

    /**
     * @return array<string, mixed>
     */
    public function index(int $userId): array
    {
        $page = $this->visible->page($userId, self::CENTERS_PER_PAGE);
        // The items of a page are already a list.
        $ids = array_values(array_map(fn (WorkCenterSummary $center): string => $center->id, $page->items())); // @pest-mutate-ignore: UnwrapArrayValues
        $profiles = SeatingProfile::query()->whereIn('work_center_id', $ids)->get(['work_center_id', 'sector'])->keyBy('work_center_id');

        return ['centers' => $page->through(function (WorkCenterSummary $center) use ($profiles): array {
            $sector = $profiles->get($center->id)?->sector;

            return [
                'id' => $center->id,
                'name' => $center->name,
                'activity' => $center->mainActivity,
                'sector' => $sector?->value,
                'sectorLabel' => $sector?->label(),
            ];
        })];
    }

    /**
     * @return array<string, mixed>
     */
    public function center(WorkCenterSummary $center): array
    {
        $profile = SeatingProfile::query()->where('work_center_id', $center->id)->first();

        return [
            'workCenter' => ['id' => $center->id, 'name' => $center->name, 'activity' => $center->mainActivity],
            'profile' => $profile === null ? null : [
                'sector' => $profile->sector->value,
                'sectorLabel' => $profile->sector->label(),
                'noStandingPositions' => $profile->no_standing_positions,
                'declaredOn' => $profile->declared_on->toDateString(),
                'note' => $profile->note,
            ],
            'sectors' => array_map(fn (Sector $sector): array => ['value' => $sector->value, 'label' => $sector->label()], Sector::cases()),
            'canManage' => Gate::allows('manage-seating'),
        ];
    }
}
