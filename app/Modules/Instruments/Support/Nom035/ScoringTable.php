<?php

declare(strict_types=1);

namespace App\Modules\Instruments\Support\Nom035;

/**
 * How a NOM-035 guide is scored: which items score 0 on "Siempre" (the rest score 4 on it), how items group into
 * dimensions, dimensions into domains and domains into categories, and the lower bound of Bajo, Medio, Alto and Muy
 * alto for the total and each group.
 */
final readonly class ScoringTable
{
    /**
     * @param  list<int>  $zeroOnAlways
     * @param  array<string, array{label: string, domain: string, items: list<int>}>  $dimensions
     * @param  array<string, array{label: string, category: string, cutoffs: array{0: int, 1: int, 2: int, 3: int}}>  $domains
     * @param  array<string, array{label: string, cutoffs: array{0: int, 1: int, 2: int, 3: int}}>  $categories
     * @param  array{0: int, 1: int, 2: int, 3: int}  $final
     */
    public function __construct(
        public array $zeroOnAlways,
        public array $dimensions,
        public array $domains,
        public array $categories,
        public array $final,
    ) {}
}
