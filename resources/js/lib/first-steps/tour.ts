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

type Destroyable = { destroy: () => void };

/**
 * The life of one tour at a time. `run` supersedes whatever is running or still loading; `cancel` ends it,
 * and a tour that finishes loading after that destroys itself. A failed load is ignored: the tour is only a help.
 */
export function createTourRunner<Input>(
    load: (input: Input) => Promise<Destroyable>,
): {
    run: (input: Input) => void;
    cancel: () => void;
} {
    let current: Destroyable | null = null;
    let token = 0;

    const cancel = (): void => {
        token += 1;
        current?.destroy();
        current = null;
    };

    const run = (input: Input): void => {
        cancel();
        const mine = token;

        load(input)
            .then((tour) => {
                if (mine === token) {
                    current = tour;
                } else {
                    tour.destroy();
                }
            })
            .catch(() => undefined);
    };

    return { run, cancel };
}
