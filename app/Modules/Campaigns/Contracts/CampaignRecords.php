<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Contracts;

use App\Modules\Campaigns\Data\CampaignRecord;

/**
 * The campaigns of the current company as other modules read them: what was applied, to how many, when, and how many
 * people answered each guide. Never what they answered.
 */
interface CampaignRecords
{
    /** A campaign by id; null when it does not exist or belongs to another company. */
    public function find(string $id): ?CampaignRecord;
}
