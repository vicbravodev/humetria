import { useState } from 'react';
import { useSave } from '@/hooks/use-save';
import type { CenterAnswers, OnboardingCenter } from '@/lib/onboarding';
import { update } from '@/routes/tenant/work-centers/onboarding';

/**
 * The answers of the first-time setup (board V3-Onboarding), center by center, starting from what each answered before,
 * and keeping them all at once.
 */
export function useOnboarding(tenantSlug: string, centers: OnboardingCenter[]) {
    const [answers, setAnswers] = useState<Record<string, CenterAnswers>>(() =>
        Object.fromEntries(
            centers.map(({ id, hasTeleworkers, standing, sector }) => [
                id,
                { hasTeleworkers, standing, sector },
            ]),
        ),
    );
    const saving = useSave();

    return {
        answers,
        saving,
        answer: (id: string, change: Partial<CenterAnswers>) =>
            setAnswers((current) => ({
                ...current,
                [id]: {
                    hasTeleworkers: null,
                    standing: null,
                    sector: null,
                    ...current[id],
                    ...change,
                },
            })),
        create: () =>
            saving.save(update.url(tenantSlug), {
                centers: centers.map(({ id }) => ({
                    id,
                    has_teleworkers: answers[id]?.hasTeleworkers ?? null,
                    standing: answers[id]?.standing ?? null,
                    sector: answers[id]?.sector ?? null,
                })),
            }),
    };
}
