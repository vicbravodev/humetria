<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support;

use App\Modules\Disclosure\Contracts\DisclosureStanding;
use App\Modules\Disclosure\Enums\PageLinkStatus;
use App\Modules\Disclosure\Models\PageLink;
use App\Modules\Disclosure\Models\ResultPublication;

final readonly class CompanyDisclosureStanding implements DisclosureStanding
{
    public function __construct(private PolicyStanding $policies, private DeliveryStanding $delivery) {}

    public function withPolicy(array $workCenterIds): array
    {
        $inForce = $this->policies->inForce($workCenterIds);

        return array_values(array_filter($workCenterIds, fn (string $id): bool => isset($inForce[$id])));
    }

    public function sharedEvaluations(array $campaignIds): array
    {
        return $this->sharing($campaignIds)['shared'];
    }

    public function sharing(array $campaignIds): array
    {
        $none = ['shared' => [], 'unseen' => []];

        if ($campaignIds === []) {
            return $none;
        }

        $publications = ResultPublication::query()->whereIn('campaign_id', $campaignIds)->whereNull('withdrawn_at')->get(['campaign_id', 'work_center_id']);

        if ($publications->isEmpty()) {
            return $none;
        }

        // Published is not enough: the people have to be able to see them, as in any inciso of 5.7 (C-52).
        $pageActive = PageLink::query()->first()?->status() === PageLinkStatus::Active;
        $shared = [];
        $recorded = $pageActive ? [] : $this->delivery->recordedItems(array_values(array_unique($publications->map(fn (ResultPublication $publication): string => $publication->work_center_id)->all())));

        foreach ($publications as $publication) {
            if ($pageActive || in_array('e', $recorded[$publication->work_center_id] ?? [], true)) {
                $shared[] = $publication->campaign_id;
            }
        }

        $published = $publications->map(fn (ResultPublication $publication): string => $publication->campaign_id)->all();

        return [
            'shared' => array_values(array_filter($campaignIds, fn (string $id): bool => in_array($id, $shared, true))),
            'unseen' => array_values(array_filter($campaignIds, fn (string $id): bool => in_array($id, $published, true) && ! in_array($id, $shared, true))),
        ];
    }
}
