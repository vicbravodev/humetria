import { useState } from 'react';
import { useIsMobile } from '@/hooks/use-mobile';
import { progressOf, steps } from '@/lib/survey';
import type { Answers } from '@/lib/survey';
import { resumeKey, stepHolding } from '@/lib/survey-position';
import type { SurveyInstrument } from '@/types';

/**
 * Where the person is in a guide: one question per screen on a phone, up to three of a section on a computer. The
 * survey never moves on by itself: "Siguiente" waits for every question on the screen.
 *
 * It opens at the first question still unanswered, and the place is a question, not a screen number: turning the
 * phone or widening the window regroups the screens around the same question.
 */
export function useSurveySteps(instrument: SurveyInstrument, answers: Answers) {
    const perScreen = useIsMobile() ? 1 : 3;
    const screens = steps(instrument, answers, perScreen);
    const [anchor, setAnchor] = useState(() => resumeKey(screens, answers));
    const current = stepHolding(screens, anchor);
    const step = screens[current];
    // A new screen starts at its top: on a phone the buttons are below the last option.
    const moveTo = (target: number) => {
        setAnchor(screens[target]?.keys[0]);
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
