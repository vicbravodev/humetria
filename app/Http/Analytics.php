<?php

declare(strict_types=1);

namespace App\Http;

use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Whether Google Analytics runs on this request, and what it may be told. One decision, read by two places that must
 * agree: the Blade layout (the script) and SecurityHeaders (the Google origins of the CSP). Only the landing, only
 * for a visitor without a session, only with a well-formed measurement id and only with consent.
 */
final class Analytics
{
    /** The campaign parameters of our own links: the only part of the query Google is told. */
    private const array CAMPAIGN_PARAMETERS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];

    /** Where the id of a request just stored waits, flashed, for the next visit to the landing. */
    private const string LEAD_KEY = 'analytics.lead_id';

    /**
     * A demo request was stored: the next landing visit (the one Inertia makes after the submit) learns its id, once.
     * Apart from Inertia's own flash on purpose: the submit's response keeps carrying only its toast.
     */
    public static function rememberLead(string $id): void
    {
        session()->flash(self::LEAD_KEY, $id);
    }

    /** Hands the landing, as flash, the id of the request stored just before, if any. */
    public static function flashLead(): void
    {
        $id = session()->pull(self::LEAD_KEY);

        if (is_string($id)) {
            Inertia::flash('lead', ['id' => $id]);
        }
    }

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
     * What the gtag configuration object carries, as a JS literal that cannot close the script tag: no advertising
     * signals, the page without its personal query and, only for a page of this site, the referrer as an origin.
     */
    public static function configuration(Request $request): string
    {
        $referrer = self::referrer($request);

        return self::literal([
            'allow_google_signals' => false,
            'allow_ad_personalization_signals' => false,
            'page_location' => self::pageLocation($request),
            ...($referrer === null ? [] : ['page_referrer' => $referrer]),
        ]);
    }

    /**
     * Measured with consent (Consent::analyticsAllowed), but advertising storage and signals stay denied: only
     * statistics. The contract of Consent Mode is NOT verified against Google's documentation.
     */
    public static function consentDefault(): string
    {
        return self::literal([
            'analytics_storage' => 'granted',
            'ad_storage' => 'denied',
            'ad_user_data' => 'denied',
            'ad_personalization' => 'denied',
        ]);
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

    /** @param array<string, mixed> $values */
    private static function literal(array $values): string
    {
        return json_encode($values, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
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
