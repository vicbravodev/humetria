<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support\Results;

use App\Modules\Engagement\Enums\Driver;
use App\Modules\Engagement\Enums\Priority;
use App\Modules\Engagement\Support\Bank\ItemBank;
use App\Modules\Engagement\Support\Thresholds;

/**
 * Which drivers to act on first, always for the whole company. A driver counts when enough people answered it and the
 * engagement questions, and its correlation with the engagement is positive and in the upper half of the drivers. It is
 * High when its favorable share is under the median of the drivers, Medium otherwise. The correlation never leaves
 * this class: only the order it gives.
 */
final class Priorities
{
    /** Below this variance the answers are all the same and the correlation is 0. ponytail: float noise guard, not a tunable. */
    private const float NO_VARIANCE = 1e-12;

    /**
     * @param  list<ResponseRow>  $rows
     * @param  array<string, ItemResult>  $byKey
     * @return list<array{driver: string, level: string}>
     */
    public static function compute(array $rows, array $byKey, Thresholds $thresholds): array
    {
        $impacts = [];
        foreach (Driver::cases() as $driver) {
            $impact = self::impact($driver, $rows, $thresholds);
            if ($impact !== null) {
                $impacts[$driver->value] = $impact;
            }
        }

        if (count($impacts) < 3) {
            return [];
        }

        $impactMedian = self::median(array_values($impacts));
        $favorable = self::favorableByDriver($byKey);
        $favorableMedian = self::median(array_values($favorable));
        $candidates = array_filter($impacts, fn (float $impact): bool => $impact > 0 && $impact >= $impactMedian);
        arsort($candidates);

        $priorities = [];
        foreach (array_keys($candidates) as $driver) {
            $high = isset($favorable[$driver]) && $favorable[$driver] < $favorableMedian;
            $priorities[] = ['driver' => $driver, 'level' => ($high ? Priority::High : Priority::Medium)->value];
        }

        return $priorities;
    }

    /**
     * The Pearson correlation between what the people answered to the driver and to the engagement, or `null` when fewer
     * than the minimum of a trend answered both.
     *
     * @param  list<ResponseRow>  $rows
     */
    private static function impact(Driver $driver, array $rows, Thresholds $thresholds): ?float
    {
        $keys = array_map(fn ($item): string => $item->key, ItemBank::ofDriver($driver));
        $x = $y = [];
        foreach ($rows as $row) {
            $driverScore = self::average($row->answers, $keys);
            $engagement = self::average($row->answers, ItemBank::INDEX);
            if ($driverScore !== null && $engagement !== null) {
                $x[] = $driverScore;
                $y[] = $engagement;
            }
        }

        if (! $thresholds->trend(count($x))) {
            return null;
        }

        $meanX = array_sum($x) / count($x);
        $meanY = array_sum($y) / count($y);
        $covariance = $varianceX = $varianceY = 0.0;
        foreach ($x as $i => $value) {
            $covariance += ($value - $meanX) * ($y[$i] - $meanY);
            $varianceX += ($value - $meanX) ** 2;
            $varianceY += ($y[$i] - $meanY) ** 2;
        }

        return $varianceX < self::NO_VARIANCE || $varianceY < self::NO_VARIANCE ? 0.0 : $covariance / sqrt($varianceX * $varianceY);
    }

    /**
     * @param  array<string, int|null>  $answers
     * @param  list<string>  $keys
     */
    private static function average(array $answers, array $keys): ?float
    {
        $values = [];
        foreach ($keys as $key) {
            if (($answers[$key] ?? null) !== null) {
                $values[] = $answers[$key];
            }
        }

        return $values === [] ? null : array_sum($values) / count($values);
    }

    /**
     * The favorable share of each driver: the average of its shown questions.
     *
     * @param  array<string, ItemResult>  $byKey
     * @return array<string, float>
     */
    private static function favorableByDriver(array $byKey): array
    {
        $byDriver = [];
        foreach (Driver::cases() as $driver) {
            $shown = [];
            foreach (ItemBank::ofDriver($driver) as $item) {
                $favorable = isset($byKey[$item->key]) ? $byKey[$item->key]->favorable : null;
                if ($favorable !== null) {
                    $shown[] = $favorable;
                }
            }
            if ($shown !== []) {
                $byDriver[$driver->value] = array_sum($shown) / count($shown);
            }
        }

        return $byDriver;
    }

    /** @param  list<float>  $values */
    private static function median(array $values): float
    {
        if ($values === []) {
            return 0.0;
        }

        sort($values);
        $middle = intdiv(count($values), 2);

        return count($values) % 2 === 1 ? $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2;
    }
}
