import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { spotlightFor } from '@/lib/demos/guide';
import type { DemoGuideStep } from '@/types/demo-guide';

const MARK = 'data-guide-spotlight';

/**
 * Rings the control of the current step while its page is open (`spotlight-tour.css`), and brings it into view. A ring, not
 * driver.js's veil: the veil would catch the clicks meant for the dock and for the page while the client watches.
 */
export function useGuideSpotlight(current: DemoGuideStep | null): void {
    const { url } = usePage();
    const selector =
        current === null
            ? null
            : (spotlightFor(current, url)?.selector ?? null);

    useEffect(() => {
        const control =
            selector === null ? null : document.querySelector(selector);

        if (control === null) {
            return;
        }

        control.setAttribute(MARK, '');
        control.scrollIntoView({
            block: 'center',
            behavior: window.matchMedia('(prefers-reduced-motion: reduce)')
                .matches
                ? 'auto'
                : 'smooth',
        });

        return () => control.removeAttribute(MARK);
    }, [selector, url]);
}
