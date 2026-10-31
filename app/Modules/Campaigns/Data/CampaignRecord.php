<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Data;

/**
 * A campaign as other modules read it. Days are written as the design system writes them ("2 nov 2026"), in the display
 * time zone; a draft has no launch day and only a closed campaign has a closing one. `closesOn` is the last day it takes
 * answers, and `daysLeft` how many days there are from today to it, only while it runs.
 */
final readonly class CampaignRecord
{
    /**
     * @param  list<string>  $instruments  instrument keys, in the order they are applied
     * @param  array<string, int>  $invited  people given each guide, cancelled ones left out
     * @param  array<string, int>  $answered  people who answered each guide
     */
    public function __construct(
        public string $id,
        public string $workCenterId,
        public string $kind,
        public string $kindLabel,
        public bool $closed,
        public array $instruments,
        public ?int $workers,
        public bool $sample,
        public ?int $sampleSize,
        public ?string $launchedOn,
        public ?string $closedOn,
        public array $invited,
        public array $answered,
        public ?string $closesOn = null,
        public ?int $daysLeft = null,
    ) {}
}
