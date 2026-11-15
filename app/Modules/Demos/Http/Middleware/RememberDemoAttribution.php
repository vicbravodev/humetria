<?php

declare(strict_types=1);

namespace App\Modules\Demos\Http\Middleware;

use App\Http\SiteHost;
use App\Modules\Demos\Data\DemoTouch;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Remembers where whoever opens the landing came from, so a demo request can say it. Only GET, only on `home` (the route
 * says so): never on a page with a token. A visit with no signal writes nothing: no data is not direct traffic.
 *
 * What is kept is the campaign's own utm_* and the HOST of an external referrer, never a URL (inside this very site it
 * can be a link with a token) and never the click ids of Google Ads. Anything that is not a line of text of the right
 * size is dropped, one value at a time, without an error.
 */
final class RememberDemoAttribution
{
    public const string SESSION_KEY = 'demo_attribution';

    private const int MAX_UTM = 100;

    private const int MAX_HOST = 255;

    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET')) {
            $this->remember($request);
        }

        return $next($request);
    }

    /** What the session remembers: the first visit with a signal, as the touch read back from it. */
    public static function fromSession(Request $request): DemoTouch
    {
        return self::touchFrom($request->session()->get(self::SESSION_KEY));
    }

    private function remember(Request $request): void
    {
        $touch = self::visit($request);

        if (! $touch->isEmpty() && self::fromSession($request)->isEmpty()) {
            $request->session()->put(self::SESSION_KEY, self::toArray($touch));
        }
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
