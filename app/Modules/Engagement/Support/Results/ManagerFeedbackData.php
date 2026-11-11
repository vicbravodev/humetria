<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support\Results;

use App\Modules\Engagement\Enums\SummaryStatus;

/**
 * What a manager is told about the group of people who answered about them: the share favorable of each question they
 * may be shown, against the company's, the best and the worst, whether the group is their direct reports or the whole
 * team below them, and the AI summary of the comments. No number of people invited or answering, and no text of a
 * comment.
 */
final readonly class ManagerFeedbackData
{
    /**
     * @param  list<array{key: string, text: string, favorable: int, vsCompany: int|null}>  $items
     * @param  list<array{key: string, text: string, favorable: int, vsCompany: int|null}>  $strengths
     * @param  list<array{key: string, text: string, favorable: int, vsCompany: int|null}>  $opportunities
     * @param  array{valued: list<array{theme: string, count: int}>, change: list<array{theme: string, count: int}>}|null  $themes
     */
    public function __construct(
        public array $items,
        public array $strengths,
        public array $opportunities,
        public bool $line,
        public ?SummaryStatus $summaryStatus,
        public ?array $themes,
    ) {}
}
