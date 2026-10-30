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

    private static function zone(): string
    {
        return config()->string('app.display_timezone');
    }
}
