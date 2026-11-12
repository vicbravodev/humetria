import type { DriveStep } from 'driver.js';
import type { TourBubble } from '@/lib/first-steps/steps';

/**
 * The spotlight steps of a tour. driver.js writes title and text as HTML: only the fixed copy of
 * `steps.ts` goes in, never data of the company. A bubble whose control is not on the page loses its
 * `element`, and driver.js centers it.
 */
export function driverSteps(
    bubbles: TourBubble[],
    exists: (selector: string) => boolean,
): DriveStep[] {
    return bubbles.map(({ target, title, text }) => {
        const selector = `[data-tour="${target}"]`;
        const popover = { title, description: text };

        return exists(selector) ? { element: selector, popover } : { popover };
    });
}
