import { useHttp } from '@inertiajs/react';
import { useState } from 'react';
import type { Draft, Payload, StepId } from '@/lib/complaints/form';
import {
    emptyDraft,
    payload,
    problemWith,
    stepAfter,
    stepBefore,
} from '@/lib/complaints/form';
import type { Receipt } from '@/lib/complaints/receipt';
import { parseReceipt } from '@/lib/complaints/receipt';
import { store } from '@/routes/complaints/public';

type Limits = { min: number; max: number };

/**
 * The state of the public form: the step the person is on, what they wrote, and the receipt once it was sent. Nothing is
 * stored in the browser (no cookie, no storage): closing the page forgets the draft, which is the point.
 */
export function useComplaintForm(code: string, ticket: string, limits: Limits) {
    const [step, setStep] = useState<StepId>('intro');
    const [draft, setDraft] = useState<Draft>(emptyDraft);
    const [problem, setProblem] = useState<string | undefined>(undefined);
    const [receipt, setReceipt] = useState<Receipt | undefined>(undefined);
    const http = useHttp<Payload, unknown>(payload(emptyDraft, ticket));

    const change = (changes: Partial<Draft>) => {
        setDraft((current) => ({ ...current, ...changes }));
        setProblem(undefined);
    };
    const go = (to: StepId) => {
        setStep(to);
        setProblem(undefined);
    };
    const next = () => {
        const missing = problemWith(step, draft, limits);

        if (missing === undefined) {
            go(stepAfter(step));
        } else {
            setProblem(missing);
        }
    };
    const send = () => {
        const missing = problemWith(step, draft, limits);

        if (missing !== undefined) {
            setProblem(missing);

            return;
        }

        http.transform(() => payload(draft, ticket));
        http.post(store.url({ code }), {
            onSuccess: (response) => setReceipt(parseReceipt(response)),
        }).catch(() => undefined);
    };

    return {
        step,
        draft,
        problem,
        receipt,
        errors: http.errors,
        processing: http.processing,
        change,
        next,
        back: () => go(stepBefore(step)),
        send,
    };
}
