<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Data;

/**
 * The most recent NOM-035 evaluation of a center: whether it was launched or closed, how many people it invited and how
 * many of them completed a guide. Never who.
 */
final readonly class CenterCampaignActivity
{
    public function __construct(
        public int $drafts,
        public bool $launched,
        public int $invited,
        public int $answered,
        public bool $closed,
        public ?string $campaignId,
    ) {}
}
