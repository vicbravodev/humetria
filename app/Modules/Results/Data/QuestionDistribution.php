<?php

declare(strict_types=1);

namespace App\Modules\Results\Data;

/**
 * How a yes/no question was answered. Under the minimum of respondents it is not visible and carries no count at all, not
 * even how many answered it (criterio C-23).
 */
final readonly class QuestionDistribution
{
    public function __construct(
        public int $number,
        public bool $visible,
        public int $yes = 0,
        public int $no = 0,
        public int $notApplicable = 0,
    ) {}
}
