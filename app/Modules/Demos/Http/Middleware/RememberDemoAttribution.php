<?php

declare(strict_types=1);

namespace App\Modules\Demos\Http\Middleware;

use App\Http\Consent;
use App\Http\SiteHost;
use App\Modules\Demos\Data\DemoTouch;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Remembers where whoever opens the landing came from, so a demo request can say it. Only GET, only on `home` (the route
 * says so): never on a page with a token. A visit with no signal writes nothing: no data is not direct traffic.
 *
 * What is kept is the campaign's own utm_* and the HOST of an external referrer, never a URL (inside this very site it
 * can be a link with a token) and never the click ids of Google Ads. Anything that is not a line of text of the right
 * size is dropped, one value at a time, without an error.
 *
 * Two levels. The session always keeps the first visit with a signal (the session the site already uses, 120 minutes of
 * inactivity). The cookie `humetria_acq` (encrypted, 90 days, first touch written once, last touch overwritten by each
 * visit with a signal) is written, and read, only while Consent allows it.
 */
final class RememberDemoAttribution
{
    public const string SESSION_KEY = 'demo_attribution';

    private const int MAX_UTM = 100;

    private const int MAX_HOST = 255;

    public const string COOKIE = 'humetria_acq';

    private const int COOKIE_DAYS = 90;

    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET')) {
            $this->remember($request);
        }

        return $next($request);
    }

    /** The first visit with a signal, as the session remembers it. */
    public static function fromSession(Request $request): DemoTouch
    {
        return self::touchFrom($request->session()->get(self::SESSION_KEY));
    }

    private function remember(Request $request): void
    {
        $touch = self::visit($request);

        if ($touch->isEmpty()) {
            return;
        }

        if (self::fromSession($request)->isEmpty()) {
            $request->session()->put(self::SESSION_KEY, self::toArray($touch));
        }

        if (Consent::analyticsAllowed($request)) {
            $this->writeCookie($request, $touch);
        }
    }

    /** The first touch stays (and the day it was first seen); the last one is this visit's. */
    private function writeCookie(Request $request, DemoTouch $touch): void
    {
        $stored = self::fromCookie($request);

        Cookie::queue(Cookie::make(
            self::COOKIE,
            json_encode([
                'first' => self::toArray($stored['first'] ?? $touch),
                'last' => self::toArray($touch),
                'first_seen_at' => $stored['first_seen_at'] ?? now()->getTimestamp(),
            ], JSON_THROW_ON_ERROR),
            self::COOKIE_DAYS * 24 * 60,
            '/',
            null,
            true,
            true,
            false,
            'lax',
        ));
    }

    /**
     * The cookie as the browser sent it, or null when there is none, it is not what was written (it does not decrypt,
     * is not JSON of the right shape, has no first touch) or it is older than 90 days or from the future.
     *
     * @return array{first: DemoTouch, last: DemoTouch, first_seen_at: int}|null
     */
    private static function fromCookie(Request $request): ?array
    {
        $raw = $request->cookie(self::COOKIE);
        $stored = is_string($raw) ? json_decode($raw, true, 4) : null;

        if (! is_array($stored) || ! is_int($seen = $stored['first_seen_at'] ?? null)) {
            return null;
        }

        $age = now()->getTimestamp() - $seen;
        $first = self::touchFrom($stored['first'] ?? null);

        if ($age < 0 || $age > self::COOKIE_DAYS * 86_400 || $first->isEmpty()) {
            return null;
        }

        return ['first' => $first, 'last' => self::touchFrom($stored['last'] ?? null), 'first_seen_at' => $seen];
    }

    /** The signal this visit carries. */
    private static function visit(Request $request): DemoTouch
    {
        $referer = $request->headers->get('referer');
        $host = SiteHost::hostOf($referer);

        return new DemoTouch(
            source: self::line($request->query('utm_source'), self::MAX_UTM),
            medium: self::line($request->query('utm_medium'), self::MAX_UTM),
            campaign: self::line($request->query('utm_campaign'), self::MAX_UTM),
            referrerHost: $host === null || SiteHost::owns($request, $referer) ? null : self::host($host),
        );
    }

    /** Reads a touch from whatever was stored, trusting none of it. */
    private static function touchFrom(mixed $stored): DemoTouch
    {
        if (! is_array($stored)) {
            return new DemoTouch;
        }

        return new DemoTouch(
            source: self::line($stored['source'] ?? null, self::MAX_UTM),
            medium: self::line($stored['medium'] ?? null, self::MAX_UTM),
            campaign: self::line($stored['campaign'] ?? null, self::MAX_UTM),
            referrerHost: self::host($stored['referrer_host'] ?? null),
        );
    }

    /** @return array{source: ?string, medium: ?string, campaign: ?string, referrer_host: ?string} */
    private static function toArray(DemoTouch $touch): array
    {
        return ['source' => $touch->source, 'medium' => $touch->medium, 'campaign' => $touch->campaign, 'referrer_host' => $touch->referrerHost];
    }

    /** A line of text of 1 to $max characters once trimmed: no control characters, valid UTF-8. */
    private static function line(mixed $value, int $max): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '' || preg_match('/\A[^\x00-\x1F\x7F]+\z/u', $value) !== 1 || mb_strlen($value) > $max) {
            return null;
        }

        return $value;
    }

    /** A host: lowercase, 1 to 255 characters, only what a host is made of. */
    private static function host(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $host = strtolower($value);

        return preg_match('/\A[a-z0-9._\[\]:-]{1,'.self::MAX_HOST.'}\z/', $host) === 1 ? $host : null;
    }
}
