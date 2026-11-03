<?php

declare(strict_types=1);

namespace App\Modules\Results\Data;

/**
 * The answers of a confidential survey, counted: for the center or for one area shown, with the areas that may be shown
 * and how many stay hidden. No answer of anyone, no order and no time.
 */
final readonly class SurveyDistribution
{
    /**
     * @param  list<QuestionDistribution>  $questions  empty while the group is under the minimum
     * @param  list<AreaCount>  $areas  largest first
     */
    public function __construct(
        public int $answered,
        public bool $visible,
        public array $questions,
        public array $areas,
        public int $hiddenAreas,
        public ?string $areaId,
    ) {}
}
