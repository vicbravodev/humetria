<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use Carbon\CarbonImmutable;

/**
 * The code a work center's screen shows: `{window}.{hmac}`, the window it was issued in (30 s by default,
 * config/attendance.php) and the HMAC-SHA256 with the app key over its own domain (attendance-qr, so it never collides with
 * another HMAC of the app key), the company, the center and that window. Carrying the window lets the server verify a
 * single HMAC instead of looking for the window, which is what a phone that read it without a connection needs
 * (criterio 3.3.1). With a connection the server accepts the current window and the one before (by default), so a photo of
 * the code sent around dies within a minute.
 * The format is free to change: there are no printed codes nor clients that keep one. Rotating APP_KEY only voids the
 * codes on screen for a few seconds: the screens fetch a new one.
 */
final class QrToken
{
    public static function issue(string $tenantId, string $workCenterId, CarbonImmutable $at): string
    {
        $window = self::window($at);

        return $window.'.'.self::sign($tenantId, $workCenterId, $window);
    }

    public static function verify(string $token, string $tenantId, string $workCenterId, CarbonImmutable $now): bool
    {
        $window = self::signedWindow($token, $tenantId, $workCenterId);
        $current = self::window($now);

        return $window !== null && $window <= $current && $window >= $current - max(0, config()->integer('attendance.qr.past_windows'));
    }

    /**
     * Whether a code that no longer verifies was the center's within the last day: a photo or an address opened again, not
     * a code of another center nor anything else (hallazgo 26 of docs/ui-polish-workers.md).
     */
    public static function expired(string $token, string $tenantId, string $workCenterId, CarbonImmutable $now): bool
    {
        $window = self::signedWindow($token, $tenantId, $workCenterId);
        $current = self::window($now);

        return $window !== null && ! self::verify($token, $tenantId, $workCenterId, $now) && $window <= $current && $window >= $current - intdiv(86400, self::seconds());
    }

    /**
     * When the window the code was issued in began, if its HMAC verifies for that center and that moment is not after
     * `$notAfter`; otherwise null (a code that does not verify counts as no code). It sets no limit to the code's age:
     * whoever receives the mark judges it.
     */
    public static function windowStart(string $token, string $tenantId, string $workCenterId, CarbonImmutable $notAfter): ?CarbonImmutable
    {
        $window = self::signedWindow($token, $tenantId, $workCenterId);
        $start = $window === null ? null : CarbonImmutable::createFromTimestampUTC($window * self::seconds());

        return $start !== null && $start->lessThanOrEqualTo($notAfter) ? $start : null;
    }

    /** When the screen shows the next code: the end of the window of `$at`. */
    public static function expiresAt(CarbonImmutable $at): CarbonImmutable
    {
        return CarbonImmutable::createFromTimestampUTC((self::window($at) + 1) * self::seconds());
    }

    /** The window a code names when its HMAC is the center's for it; null for a code of another center or one altered. */
    private static function signedWindow(string $token, string $tenantId, string $workCenterId): ?int
    {
        if (preg_match('/^(\d{1,12})\.([0-9a-f]{64})\z/', $token, $parts) !== 1) {
            return null;
        }

        $window = (int) $parts[1];

        return hash_equals(self::sign($tenantId, $workCenterId, $window), $parts[2]) ? $window : null;
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
