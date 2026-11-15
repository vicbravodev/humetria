<?php

declare(strict_types=1);

namespace App\Http;

use Illuminate\Http\Request;

/**
 * Which URLs are this site's own. The one rule, read by Analytics (what Google may be told about a referrer) and by the
 * demo attribution (which referrers are worth remembering): the host of the request and the configured one, with or
 * without `www`.
 */
final class SiteHost
{
    /** The lowercase host of an http(s) URL, or null when it is not one. */
    public static function hostOf(?string $url): ?string
    {
        $parts = parse_url((string) $url);

        if (! is_array($parts) || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true) || ! isset($parts['host']) || $parts['host'] === '') {
            return null;
        }

        return strtolower($parts['host']);
    }

    /** The URL as `scheme://host[:port]/`: nothing of its path, query or fragment. */
    public static function originOf(?string $url): ?string
    {
        $host = self::hostOf($url);
        $parts = parse_url((string) $url);

        if ($host === null || ! is_array($parts)) {
            return null;
        }

        return ($parts['scheme'] ?? '').'://'.$host.(isset($parts['port']) ? ':'.$parts['port'] : '').'/';
    }

    public static function owns(Request $request, ?string $url): bool
    {
        $host = self::hostOf($url);

        return $host !== null && in_array(self::bare($host), self::ownHosts($request), true);
    }

    /** @return list<string> */
    private static function ownHosts(Request $request): array
    {
        $configured = parse_url(config()->string('app.url'), PHP_URL_HOST);

        return array_values(array_filter([
            self::bare($request->getHost()),
            is_string($configured) ? self::bare(strtolower($configured)) : null,
        ]));
    }

    private static function bare(string $host): string
    {
        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }
}
