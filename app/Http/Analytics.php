<?php

declare(strict_types=1);

namespace App\Http;

use Illuminate\Http\Request;

/**
 * Whether Google Analytics runs on this request, and what it may be told. One decision, read by two places that must
 * agree: the Blade layout (the script) and SecurityHeaders (the Google origins of the CSP). Only the landing, only
 * for a visitor without a session, only with a well-formed measurement id and only with consent.
 */
final class Analytics
{
    /** The campaign parameters of our own links: the only part of the query Google is told. */
    private const array CAMPAIGN_PARAMETERS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];

    public static function measurementId(Request $request): ?string
    {
        $id = config('services.google_analytics.measurement_id');

        if (! is_string($id) || preg_match('/^G-[A-Z0-9]+\z/', $id) !== 1) {
            return null;
        }

        return $request->route()?->getName() === 'home'
            && $request->user() === null
            && Consent::analyticsAllowed($request) ? $id : null;
    }

    /**
     * `gtag` reads `document.referrer` by itself, and an external one is already origin-only by the browsers' default
     * policy. What it must not read is a page of this site (it can be a link with a token): that one goes as the origin.
     */
    public static function referrer(Request $request): ?string
    {
        $parts = parse_url((string) $request->headers->get('referer'));

        if (! is_array($parts) || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true) || ! isset($parts['host'])) {
            return null;
        }

        $host = strtolower($parts['host']);

        if (! in_array(self::bare($host), self::ownHosts($request), true)) {
            return null;
        }

        return $parts['scheme'].'://'.$host.(isset($parts['port']) ? ':'.$parts['port'] : '').'/';
    }

    /** The landing's address without any query but its campaign parameters. */
    public static function pageLocation(Request $request): string
    {
        $campaign = [];

        foreach (self::CAMPAIGN_PARAMETERS as $name) {
            $value = $request->query($name);

            if (is_string($value) && $value !== '' && mb_strlen($value) <= 100) {
                $campaign[$name] = $value;
            }
        }

        return $request->getSchemeAndHttpHost().$request->getPathInfo().($campaign === [] ? '' : '?'.http_build_query($campaign));
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
