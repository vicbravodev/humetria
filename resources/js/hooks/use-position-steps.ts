import { useState } from 'react';
import { missingInStep, textValues } from '@/lib/seating/position-steps';

/** The step of the position wizard and the required fields «Siguiente» found empty in it. */
export function usePositionSteps() {
    const [step, setStep] = useState(1);
    const [missing, setMissing] = useState<string[]>([]);

    const goTo = (to: number) => {
        setMissing([]);
        setStep(to);
    };
    // «Siguiente» does not move on over a required field still empty: it marks it.
    const next = (form: HTMLFormElement | null) => {
        const left = missingInStep(
            step,
            textValues(new FormData(form ?? undefined)),
        );

        setMissing(left);

        if (left.length === 0) {
            setStep(step + 1);
        }
    };

    return { step, missing, goTo, next };
}
