import { useState } from 'react';
import { useSave } from '@/hooks/use-save';
import { advance } from '@/lib/telework/applies';
import type { AppliesAnswer, AppliesQuestion } from '@/lib/telework/applies';

type Step = 'intro' | AppliesQuestion | 'result';

const NONE: Record<AppliesQuestion, AppliesAnswer | null> = {
    outside: null,
    share: null,
};

/**
 * The steps of «¿Te aplica la NOM-037?» (board V2-Aplica): the start, each question, and the result once the answer is
 * kept on the server. A center that already answered opens on its result.
 */
export function useTeleworkApplies(answered: boolean, url: string) {
    const [step, setStep] = useState<Step>(answered ? 'result' : 'intro');
    const [answers, setAnswers] = useState(NONE);
    const saving = useSave();

    const restart = () => {
        setAnswers(NONE);
        setStep('outside');
    };

    const next = () => {
        if (step === 'intro' || step === 'result') {
            return;
        }

        const outcome = advance(answers[step]);

        if (outcome !== null && 'ask' in outcome) {
            setStep(outcome.ask);
        } else if (outcome !== null) {
            saving.save(url, { has_teleworkers: outcome.declare }, () =>
                setStep('result'),
            );
        }
    };

    return {
        step,
        answers,
        answer: (question: AppliesQuestion, answer: AppliesAnswer) =>
            setAnswers({ ...answers, [question]: answer }),
        start: () => setStep('outside'),
        back: () => setStep(step === 'share' ? 'outside' : 'intro'),
        next,
        restart,
        saving,
    };
}
