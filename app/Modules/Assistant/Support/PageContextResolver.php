<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Support;

use App\Modules\Assistant\Data\PageContext;
use App\Modules\Campaigns\Contracts\CampaignRecords;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Illuminate\Support\Str;

/**
 * Checks the page the browser says the person has open: a campaign or a work center of the current company. Anything
 * else, including one of another company, is no context at all, exactly like one that does not exist.
 */
final readonly class PageContextResolver
{
    public function __construct(private CampaignRecords $campaigns, private WorkCenters $workCenters) {}

    public function resolve(mixed $raw): ?PageContext
    {
        if (! is_array($raw)) {
            return null;
        }

        $kind = $raw['kind'] ?? null;
        $id = $raw['id'] ?? null;

        if (! is_string($kind) || ! is_string($id) || ! Str::isUuid($id)) {
            return null;
        }

        return match ($kind) {
            'campaign' => $this->campaign($id),
            'work_center' => $this->workCenter($id),
            default => null,
        };
    }

    private function campaign(string $id): ?PageContext
    {
        $campaign = $this->campaigns->find($id);
        $center = $campaign === null ? null : $this->workCenters->profile($campaign->workCenterId);

        return $campaign === null || $center === null ? null : new PageContext('campaign', $campaign->id, "{$center->name} · {$campaign->kindLabel}");
    }

    private function workCenter(string $id): ?PageContext
    {
        $center = $this->workCenters->profile($id);

        return $center === null ? null : new PageContext('work_center', $center->id, $center->name);
    }
}
