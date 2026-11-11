<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support;

use App\Modules\Results\Contracts\SmallGroups;

/**
 * The minimums of Clima y pulso. The minimum people per group is the platform's, so it is asked of SmallGroups and never
 * written here; this class only adds the two stricter ones of this module: comments and trends.
 */
final readonly class Thresholds
{
    public function __construct(private SmallGroups $groups) {}

    /** Whether a group of $n people may be shown. */
    public function met(int $n): bool
    {
        return $this->groups->visibleKeys(['n' => $n]) !== [];
    }

    /**
     * The groups that may be shown, with the complementary suppression of Results.
     *
     * @param  array<string, int>  $countByGroup
     * @return list<string>
     */
    public function visible(array $countByGroup): array
    {
        return $this->groups->visibleKeys($countByGroup);
    }

    /** Whether the comments of a group of $n people may be shown. */
    public function comments(int $n): bool
    {
        return $this->met($n) && $n >= config()->integer('engagement.min_comments');
    }

    /** Whether $n responses are enough for the recommendation index and the arrows of a trend. */
    public function trend(int $n): bool
    {
        return $n >= config()->integer('engagement.min_trend');
    }
}
