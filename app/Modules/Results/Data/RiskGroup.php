<?php

declare(strict_types=1);

namespace App\Modules\Results\Data;

/**
 * A category or a domain of the Guía II or III: how many questionnaires fall in each risk level, from Nulo to Muy alto.
 */
final readonly class RiskGroup
{
    /**
     * @param  list<int>  $levels
     */
    public function __construct(
        public string $key,
        public string $label,
        public ?string $category,
        public array $levels,
    ) {}
}
