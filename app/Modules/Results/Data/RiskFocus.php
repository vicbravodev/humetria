<?php

declare(strict_types=1);

namespace App\Modules\Results\Data;

use App\Modules\Instruments\Data\RiskLevel;

/**
 * A place of an evaluation that needs a Programa de intervención (num. 8.3, II.4, III.4): the final score, a category or
 * a domain with at least one questionnaire in Medio or more. It carries the highest level present and how many
 * questionnaires are in Medio or more: figures Results already shows, nothing about a person. `scope` is `final`,
 * `category` or `domain`.
 */
final readonly class RiskFocus
{
    public function __construct(
        public string $scope,
        public string $key,
        public string $label,
        public ?string $category,
        public RiskLevel $level,
        public int $questionnaires,
    ) {}
}
