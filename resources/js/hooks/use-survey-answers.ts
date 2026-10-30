import { useEffect, useState } from 'react';
import { readAnswers } from '@/lib/survey';
import type { Answers } from '@/lib/survey';

/**
 * A guide's answers while the person answers it, kept only on this device (localStorage) so a reload or a lost signal
 * keeps them. The server receives them once, whole, when the person sends the guide.
 */
export function useSurveyAnswers(key: string) {
    const [answers, setAnswers] = useState<Answers>(() =>
        readAnswers(window.localStorage.getItem(key)),
    );

    useEffect(() => {
        window.localStorage.setItem(key, JSON.stringify(answers));
    }, [key, answers]);

    return {
        answers,
        answer: (question: string, value: string) =>
            setAnswers((current) => ({ ...current, [question]: value })),
        forget: () => window.localStorage.removeItem(key),
    };
}
