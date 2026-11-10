import { useState } from 'react';
import { policyChanges } from '@/lib/attendance/policy-preview';
import type { AttendancePolicy } from '@/types';

/** The policy as RH edits it before saving: the draft, how many settings differ from the saved ones, and going back to them. */
export function usePolicyDraft(saved: AttendancePolicy) {
    const [draft, setDraft] = useState(saved);

    return {
        draft,
        change: (patch: Partial<AttendancePolicy>) =>
            setDraft((current) => ({ ...current, ...patch })),
        discard: () => setDraft(saved),
        changes: policyChanges(saved, draft),
    };
}
