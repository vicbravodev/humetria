<?php

declare(strict_types=1);

namespace App\Modules\Results\Data;

/**
 * A row of the Programa de intervención as the report prints it: what it attends, the highest level that led to it (none
 * when HR added it), HR's analysis, its actions and its follow-up evaluation with its day.
 */
final readonly class ProgramOutlineItem
{
    /**
     * @param  list<ProgramOutlineAction>  $actions
     */
    public function __construct(
        public string $label,
        public string $scope,
        public ?string $level,
        public ?string $analysis,
        public ?string $followUp,
        public array $actions,
    ) {}
}
