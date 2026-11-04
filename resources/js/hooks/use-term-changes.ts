import { useState } from 'react';
import type { TermChangeForm } from '@/lib/compensation/scenarios';
import {
    addTermChange,
    removeTermChange,
    setTermChange,
} from '@/lib/compensation/scenarios';

type BenefitOption = { value: string; label: string; unit: string };

/** The benefits a scenario changes while they are picked and typed: add one, change its value, remove it. */
export function useTermChanges(initial: TermChangeForm[]) {
    const [changes, setChanges] = useState(initial);

    return {
        changes,
        add: (option: BenefitOption) =>
            setChanges((current) => addTermChange(current, option)),
        remove: (benefitId: string) =>
            setChanges((current) => removeTermChange(current, benefitId)),
        change: (benefitId: string, patch: Partial<TermChangeForm>) =>
            setChanges((current) => setTermChange(current, benefitId, patch)),
    };
}
