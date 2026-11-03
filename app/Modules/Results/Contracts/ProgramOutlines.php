<?php

declare(strict_types=1);

namespace App\Modules\Results\Contracts;

use App\Modules\Results\Data\ProgramOutline;

/**
 * The Programa de intervención of an evaluation of the current company, as the report (num. 7.7 g) and the results page
 * show it. The port is Results' and Interventions implements it: Interventions already reads Results, so the arrow stays one
 * way and Results never imports a module that imports it.
 */
interface ProgramOutlines
{
    /** The Programa of the evaluation, or null when it has none (or the evaluation is not of this company). */
    public function forCampaign(string $campaignId): ?ProgramOutline;
}
