<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Contracts;

use App\Modules\Campaigns\Data\CampaignsInCourse;

/**
 * The evaluations of the current company still open: whether a work center has one, which keeps it from being
 * archived, and those in course, which the frame counts on every page.
 */
interface OpenCampaigns
{
    /** A NOM-035 evaluation in draft or active, or any active campaign. */
    public function existFor(string $workCenterId): bool;

    /** How many campaigns are in course and how close the soonest is to its last day, in one query. */
    public function inCourse(): CampaignsInCourse;
}
