<?php

declare(strict_types=1);

namespace App\Modules\Results\Data;

/**
 * An area shown: how many questionnaires it has and how many fall in each level of the final score, Nulo to Muy alto.
 */
final readonly class AreaResult
{
    /**
     * @param  list<int>  $final
     */
    public function __construct(
        public string $id,
        public string $name,
        public int $answered,
        public array $final,
    ) {}
}
