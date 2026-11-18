import type { CaptureResult, PostHogConfig } from 'posthog-js';
import { field, readBlock, type ConfigSource } from '@/lib/analytics';
import type { ErrorTrackingConfig } from '@/lib/error-tracking';

/** What records: PostHog's build with its recorder inside, so no script is ever loaded from outside. */
export type RecordingEngine = {
    init: (token: string, options: Partial<PostHogConfig>) => unknown;
    startSessionRecording: () => void;
    stopSessionRecording: () => void;
};

type Start = { load: () => Promise<RecordingEngine>; origin: string };

/**
 * The pages never recorded, by their component: the complaints and their cases, the Guía I (by event and the list to
 * refer), Humi and the personal account. Humi's dock, present on every page, is blocked with `ph-no-capture`.
 */
const UNRECORDED = [
    'complaints/',
    'campaigns/trauma',
    'results/screenings',
    'assistant/',
    'settings/',
];

/** Attributes that say how the page looks, not who or what is on it: everything else is masked. */
const KEPT_ATTRIBUTES = new Set([
    'class',
    'style',
    'id',
    'role',
    'type',
    'rel',
    'tabindex',
    'disabled',
    'hidden',
    'width',
    'height',
    'viewbox',
    'd',
    'fill',
    'stroke',
    'xmlns',
    'points',
    'cx',
    'cy',
    'r',
    'x',
    'y',
    'rx',
    'ry',
    'x1',
    'x2',
    'y1',
    'y2',
    'transform',
    'colspan',
    'rowspan',
]);

/**
 * The rest that only says how the page looks: rrweb's own (`_cssText` is the page's CSS inlined, without which the player
 * paints it unstyled; `rr_*` keeps scroll, sizes and media), the ARIA states (not its labels) and Radix's data states.
 */
const LOOK_ATTRIBUTE =
    /^(_csstext|rr_[a-z]+|aria-(?!label|description|valuetext)[a-z]+|data-(state|slot|side|align|orientation|disabled|selected|highlighted))$/;

/** Our own static files (styles, images of the product): the player needs them to paint the page as it was. */
const OWN_ASSET = /^\/(build|images|fonts)\//;

/** What the recording carries to PostHog: the frames and their session, nothing about the page or the person. */
const KEPT_PROPERTIES = new Set([
    'token',
    'distinct_id',
    '$session_id',
    '$window_id',
    '$snapshot_data',
    '$snapshot_bytes',
    '$snapshot_source',
    '$lib',
    '$lib_version',
]);

let start: Start | null = null;
let config: ErrorTrackingConfig | null = null;
let engine: RecordingEngine | null = null;
let current: string | null = null;

function isObject(value: unknown): value is object {
    return typeof value === 'object' && value !== null;
}

function prop(value: unknown, name: string): unknown {
    return isObject(value) ? Reflect.get(value, name) : undefined;
}

/** The token and host of the error block, only when the server switched recording on (App\Http\Analytics). */
export function readRecordingConfig(
    source: ConfigSource,
): ErrorTrackingConfig | null {
    const value = readBlock(source, 'error-tracking-config');

    if (value === null || Reflect.get(value, 'recording') !== true) {
        return null;
    }

    const token = field(value, 'token');
    const host = field(value, 'host');

    return token === undefined || host === undefined ? null : { token, host };
}

/** A page of a company's panel, with somebody signed in as themself, that is not one of the never recorded. */
export function recordable(page: unknown): boolean {
    const component = prop(page, 'component');
    const props = prop(page, 'props');

    return (
        typeof component === 'string' &&
        isObject(prop(props, 'tenant')) &&
        isObject(prop(prop(props, 'auth'), 'user')) &&
        prop(props, 'impersonator') == null &&
        !UNRECORDED.some((prefix) => component.startsWith(prefix))
    );
}

/** Every attribute that could carry a name, an address or a value becomes `*`; the look stays. */
export function maskAttribute(name: string, value: string): string {
    const lower = name.toLowerCase();
    const kept =
        KEPT_ATTRIBUTES.has(lower) ||
        LOOK_ATTRIBUTE.test(lower) ||
        ((lower === 'href' || lower === 'src') && OWN_ASSET.test(value));

    return kept ? value : '*';
}

/** The address the player shows: the page's component, never the path with the company and the ids. */
export function replayUrl(origin: string, page: () => string | null) {
    return <Request extends { name?: string }>(request: Request): Request => ({
        ...request,
        name: `${origin}/${page() ?? ''}`,
    });
}

/** Only the recording leaves this instance, with only what PostHog needs to play it. */
export function snapshotOnly(
    event: CaptureResult | null,
): CaptureResult | null {
    if (event === null || event.event !== '$snapshot') {
        return null;
    }

    return {
        ...event,
        properties: Object.fromEntries(
            Object.entries(event.properties).filter(([name]) =>
                KEPT_PROPERTIES.has(name),
            ),
        ),
    };
}

/** Anonymous and masked: a random id per load kept in memory, no profile, no IP, nothing captured but the replay. */
export function recordingOptions(
    recording: ErrorTrackingConfig,
    origin: string,
    page: () => string | null,
): Partial<PostHogConfig> {
    return {
        api_host: recording.host,
        person_profiles: 'never',
        persistence: 'memory',
        ip: false,
        autocapture: false,
        capture_pageview: false,
        capture_pageleave: false,
        capture_exceptions: false,
        capture_performance: false,
        capture_heatmaps: false,
        capture_dead_clicks: false,
        rageclick: false,
        disable_surveys: true,
        save_referrer: false,
        save_campaign_params: false,
        mask_personal_data_properties: true,
        enable_recording_console_log: false,
        disable_external_dependency_loading: true,
        disable_session_recording: false,
        session_recording: {
            maskAllInputs: true,
            maskTextSelector: '*',
            blockClass: 'ph-no-capture',
            recordHeaders: false,
            recordBody: false,
            recordCrossOriginIframes: false,
            maskAttributeFn: maskAttribute,
            maskCapturedNetworkRequestFn: replayUrl(origin, page),
        },
        before_send: snapshotOnly,
    };
}

/** On each page: records it if it may, stops on one it must not; the recorder downloads on the first that may. */
export async function followPage(page: unknown): Promise<void> {
    const component = prop(page, 'component');

    current = typeof component === 'string' ? component : null;

    if (!recordable(page)) {
        engine?.stopSessionRecording();

        return;
    }

    if (engine !== null) {
        engine.startSessionRecording();

        return;
    }

    if (start === null || config === null) {
        return;
    }

    const loaded = await start.load();

    loaded.init(
        config.token,
        recordingOptions(config, start.origin, () => current),
    );
    engine = loaded;
}

/** Starts following the pages when the server switched recording on; without it nothing is downloaded. */
export async function startSessionRecording(
    source: ConfigSource,
    page: unknown,
    how: Start,
): Promise<boolean> {
    start = null;
    engine = null;
    config = readRecordingConfig(source);

    if (config === null) {
        return false;
    }

    start = how;
    await followPage(page);

    return true;
}
