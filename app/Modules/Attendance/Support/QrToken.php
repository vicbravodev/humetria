<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use Carbon\CarbonImmutable;

/**
 * The code a work center's screen shows: HMAC-SHA256 with the app key over its own domain (attendance-qr, so it never
 * collides with another HMAC of the app key), the company, the center and the 30 s window.
 * The server accepts the current window and the one before, so a photo of the code sent around dies within a minute.
 * Rotating APP_KEY only voids the codes on screen for a few seconds: the screens fetch a new one.
 */
final class QrToken
{
    private const int WINDOW_SECONDS = 30;

    public static function issue(string $tenantId, string $workCenterId, CarbonImmutable $at): string
    {
        return self::sign($tenantId, $workCenterId, self::window($at));
    }

    public static function verify(string $token, string $tenantId, string $workCenterId, CarbonImmutable $now): bool
    {
        $window = self::window($now);

        return hash_equals(self::sign($tenantId, $workCenterId, $window), $token)
            || hash_equals(self::sign($tenantId, $workCenterId, $window - 1), $token);
    }

    /** When the screen shows the next code: the end of the window of `$at`. */
    public static function expiresAt(CarbonImmutable $at): CarbonImmutable
    {
        return CarbonImmutable::createFromTimestampUTC((self::window($at) + 1) * self::WINDOW_SECONDS);
    }

    private static function window(CarbonImmutable $at): int
    {
        return intdiv($at->getTimestamp(), self::WINDOW_SECONDS);
    }

    private static function sign(string $tenantId, string $workCenterId, int $window): string
    {
        return hash_hmac('sha256', "attendance-qr|{$tenantId}|{$workCenterId}|{$window}", config()->string('app.key'));
    }
}
