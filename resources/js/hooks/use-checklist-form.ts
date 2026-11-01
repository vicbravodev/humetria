import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import PortalChecklistController from '@/actions/App/Modules/Telework/Http/Controllers/PortalChecklistController';
import {
    isComplete,
    pendingQuestions,
    photoFields,
    submission,
    visibleQuestions,
} from '@/lib/telework/answers';
import type { ChecklistState } from '@/lib/telework/answers';
import type { PortalAnswer, PortalQuestion } from '@/types';

const EMPTY: ChecklistState = {
    consent: null,
    answers: {},
    notes: {},
    photos: {},
    specialNeeds: '',
};

/**
 * The answers of one checklist while the person fills it in, and its single send. Nothing is kept on the device and
 * nothing goes to the server until they send: the page never moves on by itself.
 */
export function useChecklistForm(
    token: string,
    applicationId: string,
    questions: PortalQuestion[],
) {
    const [state, setState] = useState<ChecklistState>(EMPTY);
    const form = useForm(submission(questions, state));
    const errors: Partial<Record<string, string>> = form.errors;
    const [triedToSend, setTriedToSend] = useState(false);

    const change = (next: Partial<ChecklistState>) =>
        setState((current) => ({ ...current, ...next }));

    return {
        state,
        errors,
        processing: form.processing,
        shown: visibleQuestions(questions, state.consent),
        pending: triedToSend ? pendingQuestions(questions, state) : [],
        decide: (consent: boolean) => change({ consent }),
        answer: (question: string, value: PortalAnswer) =>
            change({ answers: { ...state.answers, [question]: value } }),
        note: (question: string, value: string) =>
            change({ notes: { ...state.notes, [question]: value } }),
        photos: (question: string, files: File[]) =>
            change({ photos: { ...state.photos, [question]: files } }),
        specialNeeds: (value: string) => change({ specialNeeds: value }),
        send: () => {
            setTriedToSend(true);

            if (!isComplete(questions, state)) {
                return;
            }

            form.transform(() => {
                const { photos, ...fields } = submission(questions, state);

                return { ...fields, ...photoFields(photos) };
            });
            form.post(
                PortalChecklistController.store.url({
                    token,
                    application: applicationId,
                }),
                { forceFormData: true },
            );
        },
    };
}
