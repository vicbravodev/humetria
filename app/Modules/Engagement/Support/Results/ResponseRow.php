<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support\Results;

use App\Modules\Engagement\Support\Audience\Segment;

/** One anonymous response, as the calculation reads it: its coarse segment and its answers (`null` = omitted). */
final readonly class ResponseRow
{
    /** @param  array<string, int|null>  $answers */
    public function __construct(
        public Segment $segment,
        public array $answers,
    ) {}
}
