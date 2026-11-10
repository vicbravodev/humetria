<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support\Results;

/**
 * What may be said of one agreement question. When `shown` is false everything else is `null`: below the minimum not
 * even how many answered is told.
 */
final readonly class ItemResult
{
    /** @param  list<int>|null  $distribution  the percentage of each option, 1 to 5 */
    public function __construct(
        public string $key,
        public bool $shown,
        public ?int $answered,
        public ?int $favorable,
        public ?int $neutral,
        public ?int $unfavorable,
        public ?array $distribution,
    ) {}
}
