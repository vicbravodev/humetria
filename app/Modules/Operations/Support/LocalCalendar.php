<?php

declare(strict_types=1);

namespace App\Modules\Operations\Support;

use Carbon\CarbonImmutable;

/** The day and the month as they are lived in Mexico (app.display_timezone), as UTC instants to query with. */
final class LocalCalendar
{
    public static function startOfToday(): CarbonImmutable
    {
        return self::local()->startOfDay()->utc();
    }

    public static function startOfMonth(): CarbonImmutable
    {
        return self::local()->startOfMonth()->utc();
    }

    public static function startOfNextMonth(): CarbonImmutable
    {
        return self::local()->startOfMonth()->addMonth()->utc();
    }

    private static function local(): CarbonImmutable
    {
        return CarbonImmutable::now()->setTimezone(config()->string('app.display_timezone'));
    }
}
