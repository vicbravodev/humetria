import type { PostHogConfig } from 'posthog-js';
import { errorSource } from '@/lib/analytics-behavior';
import {
    captureError,
    field,
    readBlock,
    type AnalyticsEngine,
    type ConfigSource,
} from '@/lib/analytics';
import {
    errorOnlyEvent,
    pageContext,
    type ErrorContext,
} from '@/lib/error-event';

/** What every page but the landing hands the browser for its error reports (App\Http\Analytics::errorConfiguration). */
export type ErrorTrackingConfig = { token: string; host: string };

const BLOCK_ID = 'error-tracking-config';

let engine: AnalyticsEngine | null = null;
let context: ErrorContext = { page: null, requestId: null };

/** The token and host the server wrote, or null without the block (no token, or the landing). */
export function readErrorTrackingConfig(
    source: ConfigSource,
): ErrorTrackingConfig | null {
    const value = readBlock(source, BLOCK_ID);
    const token = value === null ? undefined : field(value, 'token');
    const host = value === null ? undefined : field(value, 'host');

    return token === undefined || host === undefined ? null : { token, host };
}

/** The page Inertia shows now (its component and request id): what an error report says about where it happened. */
export function setErrorContext(page: unknown): void {
    context = pageContext(page);
}

export function errorContext(): ErrorContext {
    return context;
}

/**
 * Errors only: no page view, no click, no recording, no survey, no flag, nothing kept in the browser (memory) and no
 * profile of the person. `before_send` drops anything that is not an exception and the address of the page.
 */
export function errorTrackingOptions(
    config: ErrorTrackingConfig,
    origin: string,
): Partial<PostHogConfig> {
    return {
        api_host: config.host,
        autocapture: false,
        capture_pageview: false,
        capture_pageleave: false,
        capture_exceptions: false,
        capture_performance: false,
        capture_heatmaps: false,
        capture_dead_clicks: false,
        rageclick: false,
        disable_session_recording: true,
        disable_surveys: true,
        advanced_disable_flags: true,
        person_profiles: 'never',
        ip: false,
        persistence: 'memory',
        save_referrer: false,
        save_campaign_params: false,
        disable_external_dependency_loading: true,
        before_send: errorOnlyEvent(origin, errorContext),
    };
}

/** Starts the error reports when the document carries their block; without it PostHog is not even downloaded. */
export async function startErrorTracking(
    source: ConfigSource,
    load: () => Promise<AnalyticsEngine>,
    origin: string,
): Promise<boolean> {
    engine = null;

    const config = readErrorTrackingConfig(source);

    if (config === null) {
        return false;
    }

    const loaded = await load();

    loaded.init(config.token, errorTrackingOptions(config, origin));
    engine = loaded;

    return true;
}

/** An error for PostHog: the error reports of any page, or the landing's measurement on the landing. */
export function reportError(error: unknown): void {
    engine?.captureException(error);
    captureError(error);
}

function sourceOf(event: Event): unknown {
    const message: unknown = Reflect.get(event, 'message');

    return errorSource({
        error: Reflect.get(event, 'error'),
        reason: Reflect.get(event, 'reason'),
        ...(typeof message === 'string' ? { message } : {}),
    });
}

/** What breaks with nobody catching it: an uncaught error or a promise nobody caught. Returns how to stop. */
export function watchErrors(
    target: Pick<EventTarget, 'addEventListener' | 'removeEventListener'>,
    report: (error: unknown) => void,
): () => void {
    const onError = (event: Event) => report(sourceOf(event));

    target.addEventListener('error', onError);
    target.addEventListener('unhandledrejection', onError);

    return () => {
        target.removeEventListener('error', onError);
        target.removeEventListener('unhandledrejection', onError);
    };
}
