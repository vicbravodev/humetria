<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Actions;

use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Support\CampaignClock;
use App\Modules\Tenancy\Contracts\Companies;
use Illuminate\Support\Facades\Context;

/**
 * Closes, in every company, the evaluations whose last day is over in the display time zone. Links already stop
 * working at that moment; this records it. Run every hour.
 */
final readonly class CloseExpiredCampaigns
{
    public function __construct(private Companies $companies, private CloseCampaign $closeCampaign) {}

    /**
     * @return int how many were closed.
     */
    public function handle(): int
    {
        $expired = Campaign::query()->withoutGlobalScopes()
            ->where('status', CampaignStatus::Active)
            ->where('closes_on', '<', CampaignClock::today()->format('Y-m-d'))
            ->get(['id', 'tenant_id']);

        foreach ($expired as $campaign) {
            // Each in its own company, as the system: no person closed it.
            Context::scope(function () use ($campaign): void {
                $this->companies->enter($campaign->tenant_id);
                $this->closeCampaign->handle(Campaign::query()->findOrFail($campaign->id), automatic: true);
            }, ['user_id' => null]);
        }

        return $expired->count();
    }
}
