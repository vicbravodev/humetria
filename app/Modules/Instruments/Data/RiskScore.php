<?php

declare(strict_types=1);

namespace App\Modules\Instruments\Data;

/**
 * One Guía II or III scored as the norm says (II.3, III.3): the final score (Cfinal) with its level, each category
 * (Ccat) and domain (Cdom) with theirs, and the sum of each dimension, which the norm groups but does not level.
 * Each questionnaire is scored on its own; the STPS forbids averaging them (criterio C-14).
 */
final readonly class RiskScore
{
    /**
     * @param  list<GroupScore>  $categories
     * @param  list<GroupScore>  $domains
     * @param  list<array{key: string, label: string, score: int}>  $dimensions
     */
    public function __construct(
        public int $total,
        public RiskLevel $level,
        public array $categories,
        public array $domains,
        public array $dimensions,
    ) {}
}
