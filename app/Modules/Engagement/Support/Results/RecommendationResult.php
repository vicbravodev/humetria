<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support\Results;

/**
 * The recommendation index of a group: the three bands from the minimum on, the index itself only from the minimum of a
 * trend. Hidden: everything `null`.
 */
final readonly class RecommendationResult
{
    /** @param  array{detractors: int, passives: int, promoters: int}|null  $bands  percentages */
    public function __construct(
        public bool $shown,
        public ?int $index,
        public ?array $bands,
    ) {}
}
