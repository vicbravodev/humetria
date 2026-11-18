import type { CaptureResult, PostHogConfig } from 'posthog-js';
import {
    cleanProperties,
    type EventProperties,
} from '@/lib/analytics-behavior';

const LEAD_KEY = 'analytics.lead_id';
const BLOCK_ID = 'analytics-config';

/** What the landing's document hands the browser (App\Http\Analytics): the page already cleaned by the server. */
export type AnalyticsConfig = {
    token: string;
    host: string;
    pageLocation: string;
    pageReferrer?: string;
    /** The owner switched on the replay of the visit and the measure of its speed (App\Http\Analytics). */
    replay?: true;
};

/** The part of PostHog this app uses; `posthog-js` satisfies it, a test stand-in too. */
export type AnalyticsEngine = {
    init: (token: string, options: Partial<PostHogConfig>) => unknown;
    capture: (event: string, properties?: EventProperties) => void;
    captureException: (error: unknown) => void;
};

export type ConfigSource = {
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

export function field(value: object, name: string): string | undefined {
    const found: unknown = Reflect.get(value, name);

    return typeof found === 'string' && found !== '' ? found : undefined;
}

/** The JSON object of a block the server wrote in the document, or null when there is none or it is not one. */
export function readBlock(source: ConfigSource, id: string): object | null {
    const value = parse(source.getElementById(id)?.textContent ?? '');

    return typeof value === 'object' && value !== null ? value : null;
}

/** The configuration the server wrote, or null when it wrote none (no token, no consent, another page). */
export function readAnalyticsConfig(
    source: ConfigSource,
): AnalyticsConfig | null {
    const value = readBlock(source, BLOCK_ID);

    if (value === null) {
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
        ...(Reflect.get(value, 'replay') === true ? { replay: true } : {}),
    };
}

const EMAIL = /[^\s@]+@[^\s@]+\.[^\s@]+/g;
const NUMBER = /\d[\d\s().-]{6,}\d/g;
const MAX_MESSAGE = 200;

/** What an error says may quote what the visitor typed: addresses and numbers are masked, and it is cut short. */
export function scrubMessage(text: string): string {
    return text
        .replace(EMAIL, '[email]')
        .replace(NUMBER, '[number]')
        .slice(0, MAX_MESSAGE);
}

/** One entry of `$exception_list` with its message scrubbed; its type and frames (where, not what) stay. */
function scrubException(entry: unknown): unknown {
    if (typeof entry !== 'object' || entry === null) {
        return entry;
    }

    const value: unknown = Reflect.get(entry, 'value');

    return typeof value === 'string'
        ? { ...entry, value: scrubMessage(value) }
        : entry;
}

function scrubExceptions(properties: Record<string, unknown>) {
    const list = properties['$exception_list'];

    return Array.isArray(list)
        ? { ...properties, $exception_list: list.map(scrubException) }
        : properties;
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
                Object.entries(scrubExceptions(result.properties)).filter(
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

/**
 * What the landing's replay adds, only when the server switched it on: the visit recorded anonymous (what is typed is
 * masked, the console, the headers and the bodies of the network are not recorded) and how fast the page loads.
 */
function replayOptions(config: AnalyticsConfig): Partial<PostHogConfig> {
    return config.replay === true
        ? {
              disable_session_recording: false,
              enable_recording_console_log: false,
              capture_performance: { web_vitals: true, network_timing: false },
              session_recording: {
                  maskAllInputs: true,
                  blockClass: 'ph-no-capture',
                  recordHeaders: false,
                  recordBody: false,
                  recordCrossOriginIframes: false,
              },
          }
        : {};
}

/** The page view, the lead and the clicks: nothing is captured by itself and nothing loads from outside; the replay only if switched on. */
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
        ...replayOptions(config),
    };
}

/** Resolves once the page finished loading: the heavier build (the replay and the speed) never competes with the first paint. */
export function afterPageLoad(page: {
    document: { readyState: string };
    addEventListener: (name: 'load', listener: () => void) => void;
}): Promise<void> {
    return page.document.readyState === 'complete'
        ? Promise.resolve()
        : new Promise((resolve) =>
              page.addEventListener('load', () => resolve()),
          );
}

/**
 * Starts PostHog when the landing's document carries its configuration, and reports the page view. Without the block
 * (no token, no consent, a session, another page) PostHog is not even downloaded.
 */
export async function startAnalytics(
    source: ConfigSource,
    load: (replay: boolean) => Promise<AnalyticsEngine>,
): Promise<void> {
    active = null;

    const config = readAnalyticsConfig(source);

    if (config === null) {
        return;
    }

    const engine = await load(config.replay === true);

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

/** A measured moment of the landing (a click, a section, a scroll). The properties are cleaned: only identifiers and numbers. */
export function track(event: string, properties: EventProperties = {}): void {
    active?.capture(event, cleanProperties(properties));
}

/** An error of the landing, for PostHog's error tracking. Like everything here, nothing before PostHog has started. */
export function captureError(error: unknown): void {
    active?.captureException(error);
}

/** The fields of the demo form the server refused: which one failed (the funnel's friction), never what was typed or said. */
export function trackFormErrors(errors: Record<string, string>): void {
    Object.keys(errors).forEach((field) => track('demo_form_error', { field }));
}
