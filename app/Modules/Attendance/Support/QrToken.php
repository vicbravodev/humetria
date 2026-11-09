<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use Carbon\CarbonImmutable;

/**
 * The code a work center's screen shows: HMAC-SHA256 with the app key over its own domain (attendance-qr, so it never
 * collides with another HMAC of the app key), the company, the center and its window (30 s by default,
 * config/attendance.php). The server accepts the current window and the one before (by default), so a photo of the code
 * sent around dies within a minute.
 * Rotating APP_KEY only voids the codes on screen for a few seconds: the screens fetch a new one.
 */
final class QrToken
{
    public static function issue(string $tenantId, string $workCenterId, CarbonImmutable $at): string
    {
        return self::sign($tenantId, $workCenterId, self::window($at));
    }

    public static function verify(string $token, string $tenantId, string $workCenterId, CarbonImmutable $now): bool
    {
        $window = self::window($now);

        foreach (range($window, $window - max(0, config()->integer('attendance.qr.past_windows'))) as $accepted) {
            if (hash_equals(self::sign($tenantId, $workCenterId, $accepted), $token)) {
                return true;
            }
        }

        return false;
    }

    /** When the screen shows the next code: the end of the window of `$at`. */
    public static function expiresAt(CarbonImmutable $at): CarbonImmutable
    {
        return CarbonImmutable::createFromTimestampUTC((self::window($at) + 1) * self::seconds());
    }

    private static function window(CarbonImmutable $at): int
    {
        return intdiv($at->getTimestamp(), self::seconds());
    }

    private static function seconds(): int
    {
        return max(1, config()->integer('attendance.qr.window_seconds'));
    }

    private static function sign(string $tenantId, string $workCenterId, int $window): string
    {
        return hash_hmac('sha256', "attendance-qr|{$tenantId}|{$workCenterId}|{$window}", config()->string('app.key'));
    }
}
