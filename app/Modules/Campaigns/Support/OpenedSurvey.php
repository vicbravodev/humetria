<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Support;

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;

/**
 * A survey link that works: who it belongs to, in which campaign and company.
 */
final readonly class OpenedSurvey
{
    public function __construct(public Participant $participant, public Campaign $campaign, public string $company) {}
}
