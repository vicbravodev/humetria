import { useEffect, useState } from 'react';
import { SKIPPED, closedOf, resumeIndex } from '@/lib/engagement/replies';
import {
    payload,
    progressOf,
    steps,
    type SurveySection,
} from '@/lib/engagement/survey-flow';
import { readAnswers, storageKey } from '@/lib/survey';

/**
 * A person's place and replies in a climate or pulse survey. The closed answers wait on this device (a reload or a lost
 * signal keeps them) until the survey is sent whole; the comments live only in memory, because a shared phone would
 * leave them in view. It never moves on by itself: choosing an option only marks it.
 */
export function useEngagementSurvey(token: string, sections: SurveySection[]) {
    const key = storageKey(token, 'engagement');
    const list = steps(sections);
    const [stored, setStored] = useState(() =>
        readAnswers(window.localStorage.getItem(key)),
    );
    const [index, setIndex] = useState(() => resumeIndex(list, stored));
    const [comments, setComments] = useState<Record<string, string>>({});
    const [startedAt] = useState(() => Date.now());
    const step = list[index] ?? { kind: 'submit' as const };
    const answers = closedOf(stored);

    useEffect(() => {
        window.localStorage.setItem(key, JSON.stringify(stored));
    }, [key, stored]);

    const moveTo = (target: number) => {
        setIndex(Math.min(Math.max(target, 0), list.length - 1));
        window.scrollTo({ top: 0 });
    };
    const remember = (question: string, value: string) =>
        setStored((current) => ({ ...current, [question]: value }));

    return {
        step,
        progress: progressOf(list, index),
        answers,
        comments,
        isFirst: index === 0,
        answer: (question: string, value: number) =>
            remember(question, String(value)),
        skip: (question: string) => {
            remember(question, SKIPPED);
            moveTo(index + 1);
        },
        comment: (question: string, text: string) =>
            setComments((current) => ({ ...current, [question]: text })),
        next: () => moveTo(index + 1),
        back: () => moveTo(index - 1),
        replies: () =>
            payload(
                sections,
                { answers, comments },
                { startedAt, now: Date.now() },
            ),
        forget: () => window.localStorage.removeItem(key),
    };
}
