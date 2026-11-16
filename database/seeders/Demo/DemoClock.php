<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Closure;

/**
 * Tells part of the story on another day: the actions stamp what they do with that day, so a campaign closed "six months
 * ago" is dated six months before whatever day the demo is prepared.
 */
final class DemoClock
{
    public const string TIMEZONE = 'America/Mexico_City';

    /** The moment the outermost part started being told: nothing is stamped after it. */
    private static ?CarbonImmutable $told = null;

    /**
     * Runs the callback at 09:00 (Mexico City), or at another minute, of the day the relative expression gives from today,
     * and puts the clock back as it was, whether the callback returns or throws. A moment still to come (told before 09:00,
     * «today at 09:00» has not happened) is stamped at the moment the story is told instead: nothing is dated in the future.
     *
     * @template T
     *
     * @param  string  $relative  a relative date such as "-180 days".
     * @param  Closure(): T  $callback
     * @param  int  $minute  minutes after that day's midnight; past 1440 it is the next day (a night shift's end).
     * @return T
     */
    public static function at(string $relative, Closure $callback, int $minute = 9 * 60): mixed
    {
        $previous = Carbon::getTestNow();
        $outermost = self::$told === null;
        $told = self::$told ??= CarbonImmutable::now();
        $at = CarbonImmutable::now(self::TIMEZONE)->modify($relative)->startOfDay()->addMinutes($minute)->utc();
        Carbon::setTestNow($at->min($told));

        try {
            return $callback();
        } finally {
            Carbon::setTestNow($previous);

            if ($outermost) {
                self::$told = null;
            }
        }
    }
}
