import { useState } from 'react';
import { useIsMobile } from '@/hooks/use-mobile';
import { progressOf, steps } from '@/lib/survey';
import type { Answers } from '@/lib/survey';
import type { SurveyInstrument } from '@/types';

/**
 * Where the person is in a guide: one question per screen on a phone, up to three of a section on a computer. The
 * survey never moves on by itself: "Siguiente" waits for every question on the screen.
 */
export function useSurveySteps(instrument: SurveyInstrument, answers: Answers) {
    const perScreen = useIsMobile() ? 1 : 3;
    const screens = steps(instrument, answers, perScreen);
    const [index, setIndex] = useState(0);
    // A "no" to a gate can make the guide shorter than where the person stood.
    const current = Math.min(index, screens.length - 1);
    const step = screens[current];
    // A new screen starts at its top: on a phone the buttons are below the last option.
    const moveTo = (target: number) => {
        setIndex(target);
        window.scrollTo({ top: 0 });
    };

    return {
        step,
        progress:
            step === undefined
                ? undefined
                : progressOf(instrument, answers, step),
        isFirst: current === 0,
        isLast: current === screens.length - 1,
        canAdvance:
            step?.keys.every((key) => answers[key] !== undefined) ?? false,
        next: () => moveTo(Math.min(current + 1, screens.length - 1)),
        back: () => moveTo(Math.max(current - 1, 0)),
    };
}
