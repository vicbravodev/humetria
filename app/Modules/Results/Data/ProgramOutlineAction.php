<?php

declare(strict_types=1);

namespace App\Modules\Results\Data;

/**
 * An action of the Programa de intervención as the 7.7 report prints it, in words already formatted: what, at which level,
 * for whom (the center or the areas Results shows), when, who answers and where it stands. No file, no note of a step.
 */
final readonly class ProgramOutlineAction
{
    public function __construct(
        public string $title,
        public string $kind,
        public string $level,
        public string $subjects,
        public ?string $startsOn,
        public string $dueOn,
        public string $responsible,
        public string $status,
        public ?string $professional,
        public bool $overdue,
    ) {}
}
