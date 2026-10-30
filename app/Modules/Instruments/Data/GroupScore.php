<?php

declare(strict_types=1);

namespace App\Modules\Instruments\Data;

/**
 * The score of one category or domain of a questionnaire, and its risk level by the ranges of the norm. A domain
 * names the category it belongs to (Tablas 3 and 6); a category names none.
 */
final readonly class GroupScore
{
    public function __construct(public string $key, public string $label, public int $score, public RiskLevel $level, public ?string $category = null) {}
}
