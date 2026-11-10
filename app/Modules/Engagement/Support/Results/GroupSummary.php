<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support\Results;

/**
 * Everything that may be said of one group of responses (the whole company or a shown group of a cut): the figures of
 * the engagement block, the drivers and the strengths and opportunities. `responses` is the size of the group, only
 * ever read of a group that is shown.
 */
final readonly class GroupSummary
{
    /**
     * @param  array<string, ItemResult>  $byKey
     * @param  list<DriverResult>  $drivers
     * @param  array{strengths: list<ItemResult>, opportunities: list<ItemResult>}  $highlights
     */
    public function __construct(
        public int $responses,
        public array $byKey,
        public ?int $index,
        public RecommendationResult $recommendation,
        public array $drivers,
        public array $highlights,
    ) {}
}
