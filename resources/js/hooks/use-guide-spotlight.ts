import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { spotlightFor } from '@/lib/demos/guide';
import type { DemoGuideStep } from '@/types/demo-guide';

const MARK = 'data-guide-spotlight';
// How long a page may take to draw the step's control (a tab, a deferred prop) before the ring stops looking for it.
const LOOK_MS = 10_000;

/**
 * Rings the control of the current step while its page is open (`spotlight-tour.css`), and brings it into view the first
 * time. A control drawn later is ringed when it shows up. A ring, not driver.js's veil: the veil would catch the clicks
 * meant for the dock and for the page while the client watches.
 */
export function useGuideSpotlight(current: DemoGuideStep | null): void {
    const { url } = usePage();
    const selector =
        current === null
            ? null
            : (spotlightFor(current, url)?.selector ?? null);

    useEffect(() => {
        if (selector === null) {
            return;
        }

        let lit: Element | null = null;
        const light = () => {
            const control = document.querySelector(selector);

            if (control === null || control === lit) {
                return;
            }

            lit?.removeAttribute(MARK);
            control.setAttribute(MARK, '');
            control.scrollIntoView({
                block: 'center',
                behavior: window.matchMedia('(prefers-reduced-motion: reduce)')
                    .matches
                    ? 'auto'
                    : 'smooth',
            });
            lit = control;
        };
        const observer = new MutationObserver(light);
        observer.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['data-tour'],
        });
        const stop = window.setTimeout(() => observer.disconnect(), LOOK_MS);
        light();

        return () => {
            observer.disconnect();
            window.clearTimeout(stop);
            lit?.removeAttribute(MARK);
        };
    }, [selector, url]);
}
