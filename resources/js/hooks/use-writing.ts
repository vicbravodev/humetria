import { useState } from 'react';
import { useSave } from '@/hooks/use-save';
import { answered } from '@/lib/question-flow';
import type { FlowAnswers } from '@/lib/question-flow';

/**
 * The steps of a document written with questions (boards V2-Politica and V3-Doc-Riesgos): every answer and every move is
 * kept on the server at once («Se guarda en cada respuesta»), with all the answers so a save cut short by the next one loses
 * nothing. The step equal to the number of questions is the review.
 */
export function useWriting(
    url: string,
    initial: { answers: FlowAnswers; step: number },
    count: number,
) {
    const [index, setIndex] = useState(Math.min(initial.step, count));
    const [answers, setAnswers] = useState(initial.answers);
    const saving = useSave({ preserveState: true });

    const keep = (nextAnswers: FlowAnswers, step: number) =>
        saving.save(url, { answers: answered(nextAnswers), step });

    const go = (step: number) => {
        const target = Math.max(0, Math.min(step, count));
        setIndex(target);
        keep(answers, target);
    };

    return {
        index,
        answers,
        answer: (field: string, value: string | string[]) => {
            const next = { ...answers, [field]: value };
            setAnswers(next);
            keep(next, index);
        },
        go,
        back: () => go(index - 1),
        next: () => go(index + 1),
        saving,
    };
}
