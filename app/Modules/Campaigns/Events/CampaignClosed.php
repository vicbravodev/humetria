<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * A campaign stopped taking answers, by hand or because its last day ended. Dispatched once the closing is committed.
 */
final readonly class CampaignClosed implements ShouldDispatchAfterCommit
{
    public function __construct(public string $tenantId, public string $campaignId) {}
}
