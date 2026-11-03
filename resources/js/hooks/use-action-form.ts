import { useState } from 'react';
import { actionDefaults, levelAfterKind } from '@/lib/action-form';
import type { ActionCatalog, ProgramAction } from '@/types';

/**
 * What the action form decides together: the kind sets the level it usually belongs to (clinical care is the third level,
 * and the third level is only that), and the subjects decide whether areas are asked for.
 */
export function useActionForm(
    catalog: ActionCatalog,
    action: ProgramAction | null,
) {
    const start = actionDefaults(catalog, action);
    const [kind, setKind] = useState(start.kind);
    const [level, setLevel] = useState(start.level);
    const [professional, setProfessional] = useState(start.professional);
    const [subjects, setSubjects] = useState(start.subjects);

    const chooseKind = (next: string) => {
        setKind(next);
        setLevel((current) => levelAfterKind(catalog, next, current));
    };

    return {
        kind,
        level,
        professional,
        subjects,
        chooseKind,
        setLevel,
        setProfessional,
        setSubjects,
    };
}
