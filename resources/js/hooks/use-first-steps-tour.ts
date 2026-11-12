import { router, usePage } from '@inertiajs/react';
import type { Driver, PopoverDOM } from 'driver.js';
import { useCallback, useEffect, useRef, useState } from 'react';
import { openHumi } from '@/lib/assistant/open-humi';
import { bubblesOf, humiQuestion } from '@/lib/first-steps/steps';
import { rememberTour, takeTour } from '@/lib/first-steps/tour-session';
import { createTourRunner, driverSteps } from '@/lib/first-steps/tour';
import type { FirstStep } from '@/types/first-steps';

/**
 * The Humi step offers its example question as a button: it ends the tour and opens Humi with the question written, never
 * sent. Built with the DOM, never as HTML: the question is text.
 */
function questionButton(
    question: string,
    end: () => void,
): (popover: PopoverDOM) => void {
    return ({ description }) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.setAttribute('data-humi-question', '');
        button.textContent = question;
        button.addEventListener('click', () => {
            end();
            openHumi(question);
        });
        description.append(button);
    };
}

/** Runs the spotlight tour of a step; driver.js loads only when a tour starts. */
async function drive(
    step: FirstStep,
    question: string,
): Promise<{ destroy: () => void }> {
    const { driver } = await import('driver.js');
    const steps = driverSteps(
        bubblesOf(step),
        (selector) => document.querySelector(selector) !== null,
    );
    const tour: Driver = driver({
        popoverClass: 'first-steps-tour',
        animate: !window.matchMedia('(prefers-reduced-motion: reduce)').matches,
        allowClose: true,
        overlayColor: 'var(--first-steps-scrim)',
        overlayOpacity: 0.48,
        showProgress: steps.length > 1,
        // A tour of one bubble has nothing before it: no disabled «Anterior».
        showButtons:
            steps.length > 1
                ? ['next', 'previous', 'close']
                : ['next', 'close'],
        // The spotlight follows the shape it points at: Humi's launcher is a circle, the rest are buttons.
        stageRadius: step.key === 'humi' ? 34 : 8,
        progressText: '{{current}} de {{total}}',
        nextBtnText: 'Siguiente',
        prevBtnText: 'Anterior',
        doneBtnText: 'Entendido',
        steps,
        ...(step.key === 'humi' && {
            onPopoverRender: questionButton(question, () => tour.destroy()),
        }),
    });
    tour.drive();

    return tour;
}

/** The spotlight tours of the first steps: `start` runs one here or carries it to the page where its controls are. */
export function useFirstStepsTour(): { start: (step: FirstStep) => void } {
    const { url, props } = usePage();
    const slug = props.tenant?.slug;
    const steps = useRef(props.firstSteps?.steps);
    const question = useRef(humiQuestion(props.tenant?.modules));
    const [runner] = useState(() =>
        createTourRunner(({ step, ask }: { step: FirstStep; ask: string }) =>
            drive(step, ask),
        ),
    );
    const run = useCallback(
        (step: FirstStep) => runner.run({ step, ask: question.current }),
        [runner],
    );

    useEffect(() => {
        steps.current = props.firstSteps?.steps;
        question.current = humiQuestion(props.tenant?.modules);
    });

    // Only a new page asks for the remembered tour; a reloaded prop must not end the one that runs.
    useEffect(() => {
        const key = slug === undefined ? null : takeTour(slug);
        const found =
            key === null
                ? undefined
                : steps.current?.find((candidate) => candidate.key === key);

        if (found !== undefined) {
            run(found);
        }

        return runner.cancel;
    }, [url, slug, run, runner]);

    const start = useCallback(
        (next: FirstStep) => {
            if (next.href !== null && next.href !== url) {
                if (slug !== undefined) {
                    rememberTour(next.key, slug);
                }
                router.visit(next.href);

                return;
            }

            run(next);
        },
        [url, slug, run],
    );

    return { start };
}
