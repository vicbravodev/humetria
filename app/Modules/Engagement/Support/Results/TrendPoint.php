<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support\Results;

use App\Modules\Engagement\Enums\Direction;

/** A point of a series: a wave, its value and the arrow against the point before it (none when either side is too small). */
final readonly class TrendPoint
{
    public function __construct(
        public string $surveyId,
        public string $label,
        public int $value,
        public ?Direction $direction,
    ) {}
}
