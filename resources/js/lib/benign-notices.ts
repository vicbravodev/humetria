/**
 * What a browser reports as an `error` when nothing broke: a ResizeObserver callback changed what it observes within one
 * frame, so the browser delivered the rest in the next one. The layout is right and nothing is lost, but the browser
 * raises it like a crash (a map or a chart under load, as the org chart's): it would reach the error reports and fail a
 * page that has no errors.
 */
const BENIGN_NOTICES = new Set([
    'ResizeObserver loop completed with undelivered notifications.',
    'ResizeObserver loop limit exceeded',
]);

/** Drops those notices before any other listener (the error reports, the error screen) sees them. Every other error goes on. */
export function ignoreBenignNotices(
    target: Pick<EventTarget, 'addEventListener'>,
): void {
    target.addEventListener('error', (event) => {
        const message: unknown = Reflect.get(event, 'message');

        if (typeof message === 'string' && BENIGN_NOTICES.has(message)) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    });
}
