<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support;

use App\Modules\Disclosure\Contracts\DisclosureStanding;
use App\Modules\Disclosure\Models\ResultPublication;

final readonly class CompanyDisclosureStanding implements DisclosureStanding
{
    public function __construct(private PolicyStanding $policies) {}

    public function withPolicy(array $workCenterIds): array
    {
        $inForce = $this->policies->inForce($workCenterIds);

        return array_values(array_filter($workCenterIds, fn (string $id): bool => isset($inForce[$id])));
    }

    public function sharedEvaluations(array $campaignIds): array
    {
        if ($campaignIds === []) {
            return [];
        }

        $shared = ResultPublication::query()->whereIn('campaign_id', $campaignIds)->whereNull('withdrawn_at')->pluck('campaign_id')->all();

        return array_values(array_filter($campaignIds, fn (string $id): bool => in_array($id, $shared, true)));
    }
}
