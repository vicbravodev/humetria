import type { CaptureResult } from 'posthog-js';
import { scrubMessage } from '@/lib/analytics';

/** Where an error happened, as Inertia names it: the page component and the id of the request that served it. */
export type ErrorContext = { page: string | null; requestId: string | null };

/**
 * What of an exception PostHog may keep: the library, the browser and the error's level. Anything else (the address,
 * the referrer, the session, the steps before the error) is dropped, so nothing added later slips through.
 */
const KEPT = new Set([
    'token',
    'distinct_id',
    '$lib',
    '$lib_version',
    '$browser',
    '$browser_version',
    '$os',
    '$os_version',
    '$device_type',
    '$exception_level',
    '$exception_fingerprint',
]);

const URL_IN_TEXT = /https?:\/\/[^\s"'<>),]+/g;
const SOURCE_FILE = /\.(m?js|tsx?)$/;
const FRAME_URLS = new Set(['filename', 'abs_path']);

function isObject(value: unknown): value is object {
    return typeof value === 'object' && value !== null;
}

function text(value: object, name: string): string | null {
    const found: unknown = Reflect.get(value, name);

    return typeof found === 'string' ? found : null;
}

/**
 * A URL as an error report may tell it: the path of a script (where the code is), otherwise only the origin. A page's
 * path is never sent: the survey and the portals carry a one-use token in it.
 */
export function scrubUrl(url: string): string {
    try {
        const parsed = new URL(url);

        return SOURCE_FILE.test(parsed.pathname)
            ? `${parsed.origin}${parsed.pathname}`
            : `${parsed.origin}/`;
    } catch {
        return '[url]';
    }
}

/** The component and the request id of an Inertia page object; nothing else of it (its URL and props stay). */
export function pageContext(page: unknown): ErrorContext {
    if (!isObject(page)) {
        return { page: null, requestId: null };
    }

    const props: unknown = Reflect.get(page, 'props');

    return {
        page: text(page, 'component'),
        requestId: isObject(props) ? text(props, 'requestId') : null,
    };
}

function mapEntries(
    value: object,
    map: (name: string, entry: unknown) => unknown,
): Record<string, unknown> {
    return Object.fromEntries(
        Object.entries(value).map(([name, entry]) => [name, map(name, entry)]),
    );
}

function scrubFrame(frame: unknown): unknown {
    return isObject(frame)
        ? mapEntries(frame, (name, value) =>
              FRAME_URLS.has(name) && typeof value === 'string'
                  ? scrubUrl(value)
                  : value,
          )
        : frame;
}

function scrubStack(stack: unknown): unknown {
    if (!isObject(stack)) {
        return stack;
    }

    const frames: unknown = Reflect.get(stack, 'frames');

    return Array.isArray(frames)
        ? { ...stack, frames: frames.map(scrubFrame) }
        : stack;
}

/** One exception: its message masked (addresses, numbers, URLs) and the URLs of its frames cut to a script or an origin. */
function scrubEntry(entry: unknown): unknown {
    return isObject(entry)
        ? mapEntries(entry, (name, value) => {
              if (name === 'value' && typeof value === 'string') {
                  return scrubMessage(value.replace(URL_IN_TEXT, scrubUrl));
              }

              return name === 'stacktrace' ? scrubStack(value) : value;
          })
        : entry;
}

/**
 * The `before_send` of the error reports outside the landing: only `$exception` leaves, with the properties it needs,
 * the origin as its URL, the page's component and its request id (to find the server's log lines), and no person.
 */
export function errorOnlyEvent(
    origin: string,
    context: () => ErrorContext,
): (result: CaptureResult) => CaptureResult | null {
    return (result) => {
        if (result.event !== '$exception') {
            return null;
        }

        const list = result.properties['$exception_list'];
        const { page, requestId } = context();

        return {
            uuid: result.uuid,
            event: result.event,
            properties: {
                ...Object.fromEntries(
                    Object.entries(result.properties).filter(([name]) =>
                        KEPT.has(name),
                    ),
                ),
                ...(Array.isArray(list)
                    ? { $exception_list: list.map(scrubEntry) }
                    : {}),
                $current_url: `${origin}/`,
                $process_person_profile: false,
                page,
                request_id: requestId,
            },
            ...(result.timestamp === undefined
                ? {}
                : { timestamp: result.timestamp }),
        };
    };
}
