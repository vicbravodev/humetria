<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support\Results;

use App\Modules\Engagement\Enums\Direction;
use App\Modules\Engagement\Support\Thresholds;

/** What changed in a question since the previous wave, and how a group stands against the whole company. */
final class WaveComparison
{
    /**
     * The change, or `null` when a side is missing or hidden or the wording changed (a different question is not a trend).
     * The arrow needs the minimum of a trend in both waves; with less, the points alone.
     */
    public static function delta(?ItemResult $now, ?ItemResult $before, bool $sameWording, Thresholds $thresholds): ?Delta
    {
        if (! $sameWording || $now?->favorable === null || $before?->favorable === null) {
            return null;
        }

        $points = $now->favorable - $before->favorable;
        $arrow = $thresholds->trend($now->answered ?? 0) && $thresholds->trend($before->answered ?? 0);

        return new Delta($points, $arrow ? self::direction($points) : null);
    }

    /** How many points a group is above (+) or below (-) the company; `null` when either is hidden. */
    public static function againstCompany(ItemResult $group, ItemResult $company): ?int
    {
        return $group->favorable === null || $company->favorable === null ? null : $group->favorable - $company->favorable;
    }

    private static function direction(int $points): Direction
    {
        return match (true) {
            $points > 0 => Direction::Up,
            $points < 0 => Direction::Down,
            default => Direction::Same,
        };
    }
}
