<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Data;

/**
 * One kind of survey of the company. `draft` says one waits to be launched; `closesOn` is the last day of the one open
 * (null when none is), with how many were `invited` to it and how many `answered`, as RH sees them while it runs.
 * `lastClosedOn` is the day the last one closed (null when none has), and `lastResultsShown` whether enough people
 * answered it for its results to be shown.
 */
final readonly class SurveyStanding
{
    public function __construct(
        public bool $draft,
        public ?string $closesOn,
        public int $invited,
        public int $answered,
        public ?string $lastClosedOn,
        public bool $lastResultsShown,
    ) {}
}
