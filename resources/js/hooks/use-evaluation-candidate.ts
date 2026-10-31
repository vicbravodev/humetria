import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { Option } from '@/lib/option-search';
import type { WorkCenterEvaluation } from '@/types';

/**
 * The work center chosen in the new-evaluation assistant, with its people and what applies: asked of the page's
 * `candidate` alone, without touching the address. `found` is null until the chosen one arrives.
 */
export function useEvaluationCandidate(chosen: WorkCenterEvaluation | null) {
    const { candidate } = usePage<{
        candidate?: WorkCenterEvaluation | null;
    }>().props;
    const [choice, setChoice] = useState<Option | undefined>(
        chosen === null ? undefined : { value: chosen.id, label: chosen.name },
    );
    const [loading, setLoading] = useState(false);
    const known = [candidate ?? null, chosen].find(
        (center) => center !== null && center.id === choice?.value,
    );

    return {
        choice,
        found: known ?? null,
        loading,
        choose: (option: Option) => {
            setChoice(option);
            router.reload({
                only: ['candidate'],
                data: { evaluate: option.value },
                preserveUrl: true,
                onStart: () => setLoading(true),
                onFinish: () => setLoading(false),
            });
        },
    };
}
