<?php

declare(strict_types=1);

namespace App\Modules\Instruments\Data;

/**
 * The individual risk assessment of the Disposiciones (Quinta D): its edition and its seven conditions, in order.
 */
final readonly class AssessmentDefinition
{
    /**
     * @param  list<ConditionDefinition>  $conditions
     */
    public function __construct(
        public string $version,
        public array $conditions,
    ) {}
}
