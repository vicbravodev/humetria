<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support\Results;

use App\Modules\Engagement\Enums\Direction;

/** The change of a question between two waves, in percentage points. `direction` is `null` when the groups are too small for an arrow. */
final readonly class Delta
{
    public function __construct(
        public int $points,
        public ?Direction $direction,
    ) {}
}
