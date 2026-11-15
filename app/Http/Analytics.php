<?php

declare(strict_types=1);

namespace App\Http;

use App\Support\SendPostHogEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Whether PostHog runs on this request, and what it may be told. One decision, read by two places that must
 * agree: the Blade layout (the JSON block the front reads) and SecurityHeaders (the PostHog origin of the CSP). Only the
 * landing, only for a visitor without a session, only with a well-formed project token and only with consent.
 */
final class Analytics
{
    /** The PostHog ingestion host (US cloud): one constant, the one the CSP opens and the browser is told. */
    public const string HOST = 'https://us.i.posthog.com';

    /** The campaign parameters of our own links: the only part of the query PostHog is told. */
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

    /** The configured project token when it is well-formed, whatever the page: what the server itself reports with. */
    public static function serverToken(): ?string
    {
        $token = config('services.posthog.project_token');

        return is_string($token) && preg_match('/^phc_[A-Za-z0-9]{20,100}\z/', $token) === 1 ? $token : null;
    }

    /**
     * Reports an event of the server (something the browser cannot be trusted to say: a request that was stored). Same
     * rules as the browser's: a token and consent. Off the request, never tied to a person, and the properties are
     * identifiers and counts, never what someone typed.
     *
     * @param  array<string, string|int|null>  $properties
     */
    public static function capture(Request $request, string $event, array $properties = []): void
    {
        if (self::serverToken() === null || ! Consent::analyticsAllowed($request)) {
            return;
        }

        SendPostHogEvent::dispatch($event, (string) Str::uuid(), [...$properties, '$process_person_profile' => false, '$lib' => 'humetria-php']);
    }

    public static function projectToken(Request $request): ?string
    {
        $token = self::serverToken();

        if ($token === null) {
            return null;
        }

        return $request->route()?->getName() === 'home'
            && $request->user() === null
            && Consent::analyticsAllowed($request) ? $token : null;
    }

    /**
     * What the landing's JSON block carries for the browser: the token, the host, the page without its personal query
     * and, only for a page of this site, the referrer as an origin. A JS-safe literal that cannot close the script tag.
     */
    public static function configuration(Request $request, string $token): string
    {
        $referrer = self::referrer($request);

        return self::literal([
            'token' => $token,
            'host' => self::HOST,
            'page_location' => self::pageLocation($request),
            ...($referrer === null ? [] : ['page_referrer' => $referrer]),
        ]);
    }

    /**
     * The browser reads `document.referrer` by itself, and the front drops it (sanitizeEvent): only this one is sent. A page
     * of this site can be a link with a token, so it goes as the origin; an external one is not sent at all.
     */
    public static function referrer(Request $request): ?string
    {
        $referer = (string) $request->headers->get('referer');

        return SiteHost::owns($request, $referer) ? SiteHost::originOf($referer) : null;
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
}
