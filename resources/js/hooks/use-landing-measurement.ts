import { useEffect } from 'react';
import {
    clickEvent,
    createMilestones,
    errorSource,
    scrollPercent,
} from '@/lib/analytics-behavior';
import { captureError, track } from '@/lib/analytics';

const DEPTHS = [25, 50, 75, 100] as const;

/** Every click on an element tagged `data-track` (one listener for the whole landing). */
function watchClicks(): () => void {
    const onClick = (event: MouseEvent) => {
        const hit = clickEvent(
            event.target instanceof Element ? event.target : null,
        );

        if (hit !== null) {
            track(hit.event, hit.properties);
        }
    };

    document.addEventListener('click', onClick);

    return () => document.removeEventListener('click', onClick);
}

/** How far down the page the visitor got: 25, 50, 75 and 100 %, each once. */
function watchScroll(): () => void {
    const reach = createMilestones(DEPTHS, (percent) =>
        track('scroll_depth', { percent }),
    );
    const onScroll = () =>
        reach(
            scrollPercent({
                scrollY: window.scrollY,
                innerHeight: window.innerHeight,
                height: document.documentElement.scrollHeight,
            }),
        );

    window.addEventListener('scroll', onScroll, { passive: true });

    return () => window.removeEventListener('scroll', onScroll);
}

/** Each section of the landing that was actually on screen, once. */
function watchSections(): () => void {
    const seen = new Set<string>();
    const observer = new IntersectionObserver(
        (entries) => {
            for (const { isIntersecting, target } of entries) {
                if (isIntersecting && !seen.has(target.id)) {
                    seen.add(target.id);
                    track('section_viewed', { section: target.id });
                }
            }
        },
        { threshold: 0.4 },
    );

    document
        .querySelectorAll('main section[id]')
        .forEach((section) => observer.observe(section));

    return () => observer.disconnect();
}

/** The first touch of the demo form: the step between reading and asking. */
function watchFormStart(): () => void {
    let started = false;
    const onFocus = (event: FocusEvent) => {
        if (
            !started &&
            event.target instanceof Element &&
            event.target.closest('#demo-request-form') !== null
        ) {
            started = true;
            track('demo_form_started');
        }
    };

    document.addEventListener('focusin', onFocus);

    return () => document.removeEventListener('focusin', onFocus);
}

/** What breaks in the visitor's browser: an uncaught error or a promise nobody caught. */
function watchErrors(): () => void {
    const onError = (event: ErrorEvent) => captureError(errorSource(event));
    const onRejection = (event: PromiseRejectionEvent) =>
        captureError(errorSource(event));

    window.addEventListener('error', onError);
    window.addEventListener('unhandledrejection', onRejection);

    return () => {
        window.removeEventListener('error', onError);
        window.removeEventListener('unhandledrejection', onRejection);
    };
}

/**
 * What the landing measures besides the page view and the lead (App\Http\Analytics decides whether PostHog runs at all;
 * without it every call here does nothing): clicks, scroll depth, sections seen, the start of the form and browser errors.
 */
export function useLandingMeasurement(): void {
    useEffect(() => {
        const stops = [
            watchClicks(),
            watchScroll(),
            watchSections(),
            watchFormStart(),
            watchErrors(),
        ];

        return () => stops.forEach((stop) => stop());
    }, []);
}
