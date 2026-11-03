<?php

declare(strict_types=1);

namespace App\Modules\Results\Data;

/**
 * An area shown (minimum and complementary suppression already applied) with how many questionnaires fall in each level
 * of its final score, its categories and its domains, Nulo to Muy alto. Never an answer or a person.
 */
final readonly class AreaRisk
{
    /**
     * @param  list<int>  $final
     * @param  list<RiskGroup>  $categories
     * @param  list<RiskGroup>  $domains
     */
    public function __construct(
        public string $id,
        public string $name,
        public int $answered,
        public array $final,
        public array $categories,
        public array $domains,
    ) {}
}
