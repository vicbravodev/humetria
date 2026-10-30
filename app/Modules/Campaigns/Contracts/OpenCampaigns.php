<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Contracts;

/**
 * Whether a work center of the current company has an evaluation in progress, which keeps it from being archived.
 */
interface OpenCampaigns
{
    /** A NOM-035 evaluation in draft or active, or any active campaign. */
    public function existFor(string $workCenterId): bool;
}
