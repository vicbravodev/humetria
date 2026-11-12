import { router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useRef } from 'react';
import { bubblesOf } from '@/lib/first-steps/steps';
import { rememberTour, takeTour } from '@/lib/first-steps/tour-session';
import { driverSteps } from '@/lib/first-steps/tour';
import type { FirstStep } from '@/types/first-steps';

type Tour = { destroy: () => void };

/** Runs the spotlight tour of a step; driver.js loads only when a tour starts. */
async function drive(step: FirstStep): Promise<Tour> {
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
    const steps = props.firstSteps?.steps;
    const current = useRef<Tour | null>(null);

    const run = useCallback((step: FirstStep) => {
        current.current?.destroy();
        void drive(step).then((tour) => {
            current.current = tour;
        });
    }, []);

    useEffect(() => {
        const key = takeTour();
        const step =
            key === null
                ? undefined
                : steps?.find((candidate) => candidate.key === key);

        if (step !== undefined) {
            run(step);
        }

        return () => {
            current.current?.destroy();
            current.current = null;
        };
    }, [url, steps, run]);

    const start = useCallback(
        (step: FirstStep) => {
            if (step.href !== null && step.href !== url) {
                rememberTour(step.key);
                router.visit(step.href);

                return;
            }

            run(step);
        },
        [url, run],
    );

    return { start };
}
