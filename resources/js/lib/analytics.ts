import type { CaptureResult, PostHogConfig } from 'posthog-js';

const LEAD_KEY = 'analytics.lead_id';
const BLOCK_ID = 'analytics-config';

/** What the landing's document hands the browser (App\Http\Analytics): the page already cleaned by the server. */
export type AnalyticsConfig = {
    token: string;
    host: string;
    pageLocation: string;
    pageReferrer?: string;
};

/** The part of PostHog this app uses; `posthog-js` satisfies it, a test stand-in too. */
export type AnalyticsEngine = {
    init: (token: string, options: Partial<PostHogConfig>) => unknown;
    capture: (event: string) => void;
};

type ConfigSource = {
    getElementById: (id: string) => { textContent: string | null } | null;
};

/** The browser's own idea of where the visitor came from: only what the server cleaned may be sent. */
const BROWSER_REFERRER = new Set(['$referrer', '$referring_domain']);

let active: AnalyticsEngine | null = null;

function parse(text: string): unknown {
    try {
        return JSON.parse(text);
    } catch {
        return null;
    }
}

function field(value: object, name: string): string | undefined {
    const found: unknown = Reflect.get(value, name);

    return typeof found === 'string' && found !== '' ? found : undefined;
}

/** The configuration the server wrote, or null when it wrote none (no token, no consent, another page). */
export function readAnalyticsConfig(
    source: ConfigSource,
): AnalyticsConfig | null {
    const value = parse(source.getElementById(BLOCK_ID)?.textContent ?? '');

    if (typeof value !== 'object' || value === null) {
        return null;
    }

    const token = field(value, 'token');
    const host = field(value, 'host');
    const pageLocation = field(value, 'page_location');
    const pageReferrer = field(value, 'page_referrer');

    if (
        token === undefined ||
        host === undefined ||
        pageLocation === undefined
    ) {
        return null;
    }

    return {
        token,
        host,
        pageLocation,
        ...(pageReferrer === undefined ? {} : { pageReferrer }),
    };
}

/**
 * What leaves the browser: the page the server cleaned (no personal query), the referrer only if the server allowed it,
 * and nothing that builds a profile of the person ($set, $set_once, $unset).
 */
export function sanitizeEvent(
    config: AnalyticsConfig,
): (result: CaptureResult) => CaptureResult {
    return (result) => ({
        uuid: result.uuid,
        event: result.event,
        properties: {
            ...Object.fromEntries(
                Object.entries(result.properties).filter(
                    ([name]) => !BROWSER_REFERRER.has(name),
                ),
            ),
            $current_url: config.pageLocation,
            ...(config.pageReferrer === undefined
                ? {}
                : { $referrer: config.pageReferrer }),
        },
        ...(result.timestamp === undefined
            ? {}
            : { timestamp: result.timestamp }),
    });
}

/** Only the page view and the lead: nothing is captured by itself, nothing is recorded, nothing loads from outside. */
export function posthogOptions(
    config: AnalyticsConfig,
): Partial<PostHogConfig> {
    return {
        api_host: config.host,
        autocapture: false,
        capture_pageview: false,
        capture_pageleave: false,
        disable_session_recording: true,
        disable_surveys: true,
        advanced_disable_flags: true,
        person_profiles: 'never',
        ip: false,
        persistence: 'localStorage',
        save_referrer: false,
        disable_external_dependency_loading: true,
        before_send: sanitizeEvent(config),
    };
}

/**
 * Starts PostHog when the landing's document carries its configuration, and reports the page view. Without the block
 * (no token, no consent, a session, another page) PostHog is not even downloaded.
 */
export async function startAnalytics(
    source: ConfigSource,
    load: () => Promise<AnalyticsEngine>,
): Promise<void> {
    active = null;

    const config = readAnalyticsConfig(source);

    if (config === null) {
        return;
    }

    const engine = await load();

    engine.init(config.token, posthogOptions(config));
    engine.capture('$pageview');
    active = engine;
}

/**
 * Reports the conversion of a demo request the server confirmed stored. Without PostHog started (no token or no
 * consent: today) or without the id of a stored request it does nothing; the same id never reports twice, so a reload or
 * a "back" does not repeat it. The event carries no parameters: nothing about the person.
 */
export function trackLead(
    storage: Pick<Storage, 'getItem' | 'setItem'>,
    id: string | undefined,
): void {
    if (
        active === null ||
        id === undefined ||
        id === '' ||
        storage.getItem(LEAD_KEY) === id
    ) {
        return;
    }

    active.capture('generate_lead');
    storage.setItem(LEAD_KEY, id);
}
