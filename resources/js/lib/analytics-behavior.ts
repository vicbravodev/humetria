/** What an event may carry: short identifiers and numbers, never free text, an address or anything typed by a person. */
export type EventProperties = Record<string, string | number>;

type Tagged = { getAttribute: (name: string) => string | null };

/** What the click handler needs from the clicked node; any DOM element satisfies it. */
export type ClickSource = {
    closest: (selector: string) => Tagged | null;
} | null;

const IDENTIFIER = /^[A-Za-z0-9_.-]{1,40}$/;
const EVENT_NAME = /^[a-z][a-z_]{0,39}$/;

/** Only identifiers and finite numbers survive: an email, a name or a URL fails the pattern and is dropped. */
export function cleanProperties(properties: EventProperties): EventProperties {
    return Object.fromEntries(
        Object.entries(properties).filter(([, value]) =>
            typeof value === 'number'
                ? Number.isFinite(value)
                : IDENTIFIER.test(value),
        ),
    );
}

/**
 * The event of a click on (or inside) an element tagged `data-track="event_name"`, with its `data-track-target` as the
 * only property. The markup names the event, so adding a measurement never touches the handler.
 */
export function clickEvent(
    source: ClickSource,
): { event: string; properties: EventProperties } | null {
    const tagged = source?.closest('[data-track]') ?? null;
    const event = tagged?.getAttribute('data-track') ?? '';

    if (tagged === null || !EVENT_NAME.test(event)) {
        return null;
    }

    return {
        event,
        properties: cleanProperties({
            target: tagged.getAttribute('data-track-target') ?? '',
        }),
    };
}

/** Reports each milestone (25, 50…) the first time it is reached, in order. */
export function createMilestones(
    milestones: readonly number[],
    report: (milestone: number) => void,
): (reached: number) => void {
    const pending = [...milestones];

    return (reached) => {
        while (pending.length > 0 && reached >= (pending[0] ?? Infinity)) {
            const milestone = pending.shift();

            if (milestone !== undefined) {
                report(milestone);
            }
        }
    };
}

/** How much of the page the bottom edge of the window has reached, from 0 to 100. */
export function scrollPercent(view: {
    scrollY: number;
    innerHeight: number;
    height: number;
}): number {
    if (view.height <= 0) {
        return 0;
    }

    return Math.min(
        100,
        Math.round(((view.scrollY + view.innerHeight) / view.height) * 100),
    );
}

/** The error a `window` `error` or `unhandledrejection` event stands for, always an object PostHog can read. */
export function errorSource(event: {
    error?: unknown;
    message?: string;
    reason?: unknown;
}): unknown {
    const found = event.error ?? event.reason;

    if (found instanceof Error) {
        return found;
    }

    return new Error(
        typeof event.message === 'string' && event.message !== ''
            ? event.message
            : String(found),
    );
}
