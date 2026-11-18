<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support;

use App\Modules\Engagement\Models\Survey;
use App\Modules\Results\Contracts\SmallGroups;

/**
 * The minimums of Clima y pulso. The minimum people per group is the company's, so it is asked of SmallGroups and never
 * written here: the company's now for what is still to launch, the one a survey froze for that survey (`of()`). This
 * class only adds the two stricter ones of this module: comments and trends.
 */
final readonly class Thresholds
{
    public function __construct(private SmallGroups $groups) {}

    /** The same minimums for one survey: the minimum per group it froze when it launched, or the company's for a draft. */
    public function of(Survey $survey): self
    {
        return new self($this->groups->at($survey->groupMinimum()));
    }

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
