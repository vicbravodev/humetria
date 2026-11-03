<?php

declare(strict_types=1);

namespace App\Modules\Instruments\Data;

/**
 * One of the seven conditions of Quinta D, with the three options the DOF gives it.
 */
final readonly class ConditionDefinition
{
    /**
     * @param  array<int, string>  $options  The DOF's text of each option, keyed by its points (1 to 3).
     */
    public function __construct(
        public string $key,
        public string $letter,
        public string $title,
        public array $options,
    ) {}
}
