<?php

declare(strict_types=1);

namespace App\Modules\Results\Data;

/**
 * The Programa de intervención of an evaluation as the 7.7 report and the results page show it (num. 7.7 g), in words already
 * formatted, as CampaignRecord is. `asOf` is the day it was read ("20 nov 2026"): the Programa changes, the report prints
 * the state of that day. It names the areas Results shows and the people who answer, never a file, a note or an id.
 */
final readonly class ProgramOutline
{
    /**
     * @param  array{done: int, total: int}  $meter  actions done of those not cancelled
     * @param  list<ProgramOutlineItem>  $items
     */
    public function __construct(
        public string $state,
        public string $stateKey,
        public array $meter,
        public ?string $highestLevel,
        public ?string $responsible,
        public string $asOf,
        public array $items,
    ) {}
}
