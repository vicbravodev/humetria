<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

/**
 * What the NOM-035 route of a work center is read from, gathered from the modules that keep it: the policy in force (5.1),
 * where it stands in its two-year cycle (7.9) and the evaluation that runs or the draft, whether it applies only the Guía I
 * (up to 15 people, num. 2 a) and, of its latest evaluation within the cycle, the people left to channel (5.5), whether its
 * results reach the people (7.8), where its Programa stands (8.3 to 8.5, null without one) and whether its report is filled
 * in (7.7). `toChannel` is 0 for whoever may not see the Guía I.
 */
final readonly class Nom035Facts
{
    /**
     * @param  'in_course'|'up_to_date'|'overdue'|'never'  $standing
     * @param  array{answered: int, target: int, daysLeft: int}|null  $running
     * @param  array{closedOn: string|null, answered: int, target: int}|null  $closed  the latest evaluation closed within the cycle and who answered it (C-57)
     * @param  array{answered: int, target: int}|null  $guideOne  who answered the Guía I of the latest evaluation, kept visible after it closes (it applies to everyone); null when it was not applied
     * @param  bool  $unseen  the results are published but the people cannot reach them yet: no active page and no physical means (C-52)
     */
    public function __construct(
        public bool $policy,
        public string $standing,
        public ?string $until,
        public ?array $running,
        public bool $draft,
        public bool $onlyGuideOne,
        public int $toChannel,
        public bool $shared,
        public ?string $program,
        public bool $reported,
        public ?array $closed = null,
        public bool $unseen = false,
        public ?array $guideOne = null,
    ) {}
}
