<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Data;

/** The complaints received (that RH sees: none held) and, of them, the ones acknowledged since a moment. */
final readonly class ComplaintCounts
{
    public function __construct(
        public int $received,
        public int $acknowledged,
    ) {}
}
