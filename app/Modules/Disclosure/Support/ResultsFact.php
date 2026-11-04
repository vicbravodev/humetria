<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support;

use App\Modules\Campaigns\Data\CampaignRecord;
use App\Modules\Results\Data\RiskBreakdown;

/**
 * Where the results for the people stand in a work center (criterio C-54): whether 7.8 applies to it, its latest closed
 * evaluation and whether RH published that one, and the evaluation the page shows (the latest one published) with what Results
 * says of it, and whether RH chose to show the measures of its Programa with them. `guide` is the one a center of its size applies, to title the block.
 */
final readonly class ResultsFact
{
    public function __construct(
        public bool $applies,
        public string $guide,
        public ?CampaignRecord $latest,
        public ?RiskBreakdown $latestBreakdown,
        public bool $latestPublished,
        public ?CampaignRecord $shown,
        public ?RiskBreakdown $shownBreakdown,
        public bool $showMeasures = false,
    ) {}
}
