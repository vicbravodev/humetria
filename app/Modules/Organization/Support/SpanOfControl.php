<?php

declare(strict_types=1);

namespace App\Modules\Organization\Support;

use App\Modules\Organization\Enums\SpanFlag;

/**
 * How many people report directly to one: only the extremes (1 or 2, or more than 15) are marked for review.
 */
final class SpanOfControl
{
    public const int FEW_MAX = 2;

    public const int MANY_MIN = 16;

    public static function flag(int $directReports): ?SpanFlag
    {
        return match (true) {
            $directReports >= self::MANY_MIN => SpanFlag::Many,
            $directReports >= 1 && $directReports <= self::FEW_MAX => SpanFlag::Few,
            default => null,
        };
    }

    /**
     * @param  list<int>  $counts
     */
    public static function median(array $counts): ?float
    {
        if ($counts === []) {
            return null;
        }

        sort($counts);
        $middle = intdiv(count($counts), 2);

        return count($counts) % 2 === 1 ? (float) $counts[$middle] : ($counts[$middle - 1] + $counts[$middle]) / 2;
    }
}
