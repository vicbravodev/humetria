<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Contracts;

use App\Modules\Campaigns\Data\CenterCampaignActivity;

/**
 * Where the NOM-035 evaluation of a work center of the current company stands, for the demo guide: counts and states,
 * never who was invited or answered.
 */
interface CampaignActivity
{
    /** The most recent NOM-035 evaluation of the center (draft, launched or closed) and how many drafts the center has. */
    public function atCenter(string $workCenterId): CenterCampaignActivity;
}
