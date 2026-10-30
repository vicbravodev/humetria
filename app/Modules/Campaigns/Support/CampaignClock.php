<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Days as people read them, in the display time zone (CLAUDE.md: stored in UTC, shown in America/Mexico_City). A
 * campaign closes when its last day ends there.
 */
final class CampaignClock
{
    /** Today, as a date in the display time zone. */
    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(self::zone())->startOfDay();
    }

    /** The day a moment falls on in the display time zone, as Y-m-d. */
    public static function date(CarbonInterface $moment): string
    {
        return $moment->setTimezone(self::zone())->format('Y-m-d');
    }

    /** The moment a campaign whose last day is `$closesOn` stops taking answers. */
    public static function deadline(CarbonInterface $closesOn): CarbonImmutable
    {
        return CarbonImmutable::parse($closesOn->format('Y-m-d'), self::zone())->addDay()->utc();
    }

    /** Whether the last day `$closesOn` is over. */
    public static function hasPassed(CarbonInterface $closesOn): bool
    {
        return ! CarbonImmutable::now()->lessThan(self::deadline($closesOn));
    }

    /** A day as the design system writes it: "27 oct 2026". A date is taken as is; a moment, in the display zone. */
    public static function day(CarbonInterface $value, bool $isDate = false): string
    {
        $moment = $isDate ? CarbonImmutable::parse($value->format('Y-m-d'), self::zone()) : $value->setTimezone(self::zone());

        // Carbon's Spanish months end in a dot ("oct."); the design system writes "27 oct 2026".
        return str_replace('.', '', $moment->isoFormat('D MMM YYYY'));
    }

    /** Whole days from today to a campaign's last day `$closesOn`: 0 on that day and after it. */
    public static function daysLeft(CarbonInterface $closesOn): int
    {
        return max(0, self::daysBetween(self::today(), self::deadline($closesOn)->subDay()));
    }

    /**
     * How much of the days from the one `$start` falls on to the one `$end` falls on has gone by today, in percent
     * from 0 to 100, rounded down; 100 for a span of no days.
     */
    public static function elapsed(CarbonInterface $start, CarbonInterface $end): int
    {
        $from = self::dayOf($start);
        $span = self::daysBetween($from, self::dayOf($end));

        return $span <= 0 ? 100 : max(0, min(100, intdiv(100 * self::daysBetween($from, self::today()), $span)));
    }

    /** The start of the day a moment falls on, in the display time zone. */
    private static function dayOf(CarbonInterface $moment): CarbonImmutable
    {
        return self::today()->setTimestamp($moment->getTimestamp())->startOfDay();
    }

    private static function daysBetween(CarbonInterface $from, CarbonInterface $to): int
    {
        return intdiv($to->getTimestamp() - $from->getTimestamp(), 86400);
    }

    private static function zone(): string
    {
        return config()->string('app.display_timezone');
    }
}
