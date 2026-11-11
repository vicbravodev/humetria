<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support\Results;

use App\Modules\Engagement\Enums\Direction;
use App\Modules\Engagement\Support\Bank\ItemBank;
use App\Modules\Engagement\Support\Thresholds;

/**
 * Series of a figure across the closed waves of the company (clima and pulso together). Pure.
 *
 * A question is only compared between waves that asked it with the very same wording (`ref`): a wave that did not ask it,
 * asked it reworded, or hid it for being small is not a zero and does not break the series, it simply has no point. The
 * arrow needs the minimum of a trend in both points; with less, the value alone.
 */
final class Trend
{
    /** The most waves a series holds: the most recent. */
    public const int MAX_WAVES = 12;

    /**
     * @param  list<Wave>  $waves  oldest first
     * @return list<TrendPoint>
     */
    public static function item(array $waves, string $key, Thresholds $thresholds): array
    {
        $waves = self::recent($waves);
        $ref = self::latestRef($waves, $key);
        $entries = [];

        foreach ($waves as $wave) {
            $result = $wave->byKey[$key] ?? null;

            if ($ref !== null && in_array($ref, $wave->refs, true) && $result?->shown === true && $result->favorable !== null) {
                $entries[] = [$wave, $result->favorable, $result->answered ?? 0];
            }
        }

        return self::series($entries, $thresholds);
    }

    /**
     * The engagement index: only the waves where its three questions have the wording of the latest one and are all shown;
     * its arrow goes by the smallest of the three counts.
     *
     * @param  list<Wave>  $waves  oldest first
     * @return list<TrendPoint>
     */
    public static function index(array $waves, Thresholds $thresholds): array
    {
        $waves = self::recent($waves);
        $latest = $waves === [] ? null : $waves[array_key_last($waves)];
        $refs = $latest === null ? [] : array_map(fn (string $key): ?string => self::refOf($latest, $key), ItemBank::INDEX);
        $entries = [];

        foreach ($waves as $wave) {
            $value = Indices::engagement($wave->byKey);

            if (! in_array(null, $refs, true) && $value !== null && array_diff($refs, $wave->refs) === []) {
                $entries[] = [$wave, $value, min(array_map(fn (string $key): int => self::answeredOf($wave, $key), ItemBank::INDEX))];
            }
        }

        return self::series($entries, $thresholds);
    }

    /**
     * The recommendation index: only the waves that show it, which is to say with the minimum of a trend; so the arrow
     * always applies. Waves that asked the question reworded are left out.
     *
     * @param  list<Wave>  $waves  oldest first
     * @return list<TrendPoint>
     */
    public static function recommendation(array $waves): array
    {
        $waves = self::recent($waves);
        $ref = self::latestRef($waves, ItemBank::RECOMMEND);
        $points = [];
        $before = null;

        foreach ($waves as $wave) {
            $index = $wave->recommendation->index;

            if ($ref !== null && in_array($ref, $wave->refs, true) && $index !== null) {
                $points[] = new TrendPoint($wave->surveyId, $wave->label, $index, $before === null ? null : self::direction($index - $before));
                $before = $index;
            }
        }

        return $points;
    }

    /**
     * @param  list<Wave>  $waves
     * @return list<Wave>
     */
    private static function recent(array $waves): array
    {
        return array_slice($waves, -self::MAX_WAVES);
    }

    /**
     * The ref a question has in the most recent wave that asked it.
     *
     * @param  list<Wave>  $waves
     */
    private static function latestRef(array $waves, string $key): ?string
    {
        foreach (array_reverse($waves) as $wave) {
            $ref = self::refOf($wave, $key);

            if ($ref !== null) {
                return $ref;
            }
        }

        return null;
    }

    private static function answeredOf(Wave $wave, string $key): int
    {
        return isset($wave->byKey[$key]) ? $wave->byKey[$key]->answered ?? 0 : 0;
    }

    private static function refOf(Wave $wave, string $key): ?string
    {
        foreach ($wave->refs as $ref) {
            if (explode('@', $ref)[0] === $key) {
                return $ref;
            }
        }

        return null;
    }

    /**
     * @param  list<array{Wave, int, int}>  $entries  the wave, its value and how many answered it
     * @return list<TrendPoint>
     */
    private static function series(array $entries, Thresholds $thresholds): array
    {
        $points = [];
        $previous = null;
        $previousAnswered = 0;

        foreach ($entries as [$wave, $value, $answered]) {
            $arrow = $previous !== null && $thresholds->trend($answered) && $thresholds->trend($previousAnswered);
            $points[] = new TrendPoint($wave->surveyId, $wave->label, $value, $arrow ? self::direction($value - $previous) : null);
            $previous = $value;
            $previousAnswered = $answered;
        }

        return $points;
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
