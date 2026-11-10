<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support\Results;

use App\Modules\Engagement\Enums\SurveyKind;

/**
 * What one closed survey of the company says, in the terms a trend needs: the questions it asked (refs, with their
 * wording) and what each shown one came to. `label` is the month and year of its close, never the day.
 */
final readonly class Wave
{
    /**
     * @param  list<string>  $refs  `key@wording` of every question the survey asked
     * @param  array<string, ItemResult>  $byKey
     */
    public function __construct(
        public string $surveyId,
        public SurveyKind $kind,
        public string $label,
        public array $refs,
        public array $byKey,
        public RecommendationResult $recommendation,
    ) {}
}
