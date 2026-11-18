<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Support\Readers;

use App\Modules\Assistant\Contracts\ComplianceReader;
use App\Modules\Assistant\Data\AssistantScope;
use App\Modules\Assistant\Data\ComplianceStatus;
use App\Modules\Assistant\Support\PanelLinks;
use App\Modules\Engagement\Contracts\EngagementStanding;

/** Clima: the climate survey open or waiting, and whether the last one shows its results (SurveyLine). */
final readonly class ClimateReader implements ComplianceReader
{
    public function __construct(private EngagementStanding $engagement, private PanelLinks $links) {}

    public function status(AssistantScope $scope): ComplianceStatus
    {
        return SurveyLine::of('climate', 'Clima', 'clima', $this->engagement->climate(), $this->links->climate());
    }
}
