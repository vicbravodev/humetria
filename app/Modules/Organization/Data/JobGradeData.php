<?php

declare(strict_types=1);

namespace App\Modules\Organization\Data;

/**
 * A job grade as other modules read it: its category and level, never anything about the people in it.
 */
final readonly class JobGradeData
{
    public function __construct(
        public string $id,
        public string $category,
        public string $level,
    ) {}
}
