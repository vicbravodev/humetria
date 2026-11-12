import { router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { bubblesOf } from '@/lib/first-steps/steps';
import { rememberTour, takeTour } from '@/lib/first-steps/tour-session';
import { createTourRunner, driverSteps } from '@/lib/first-steps/tour';
import type { FirstStep } from '@/types/first-steps';

/** Runs the spotlight tour of a step; driver.js loads only when a tour starts. */
async function drive(step: FirstStep): Promise<{ destroy: () => void }> {
    const { driver } = await import('driver.js');
    const steps = driverSteps(
        bubblesOf(step),
        (selector) => document.querySelector(selector) !== null,
    );
    const tour = driver({
        popoverClass: 'first-steps-tour',
        animate: !window.matchMedia('(prefers-reduced-motion: reduce)').matches,
        allowClose: true,
        overlayColor: 'var(--first-steps-scrim)',
        overlayOpacity: 0.48,
        showProgress: steps.length > 1,
        progressText: '{{current}} de {{total}}',
        nextBtnText: 'Siguiente',
        prevBtnText: 'Anterior',
        doneBtnText: 'Entendido',
        steps,
    });
    tour.drive();

    return tour;
}

/** The spotlight tours of the first steps: `start` runs one here or carries it to the page where its controls are. */
export function useFirstStepsTour(): { start: (step: FirstStep) => void } {
    const { url, props } = usePage();
    const steps = useRef(props.firstSteps?.steps);
    const [runner] = useState(() => createTourRunner(drive));
    const run = runner.run;

    useEffect(() => {
        steps.current = props.firstSteps?.steps;
    });

    // Only a new page asks for the remembered tour; a reloaded prop must not end the one that runs.
    useEffect(() => {
        const key = takeTour();
        const found =
            key === null
                ? undefined
                : steps.current?.find((candidate) => candidate.key === key);

        if (found !== undefined) {
            run(found);
        }

        return runner.cancel;
    }, [url, run, runner]);

    const start = useCallback(
        (next: FirstStep) => {
            if (next.href !== null && next.href !== url) {
                rememberTour(next.key);
                router.visit(next.href);

                return;
            }

            run(next);
        },
        [url, run],
    );

    return { start };
}
