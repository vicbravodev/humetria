import { useState } from 'react';
import {
    changedCount,
    groupBySection,
    groupIsOpen,
} from '@/lib/telework/checklist';
import type { GroupChoice } from '@/lib/telework/checklist';
import type { ChecklistQuestion } from '@/types';

/**
 * The checklist's groups, each one open or closed: as `groupIsOpen` says, or as the editor chose, one at a time or all
 * at once. A group the Comisión changes after it was closed opens again, so the change is seen.
 */
export function useChecklistGroups(questions: ChecklistQuestion[]) {
    const [choices, setChoices] = useState<Record<string, GroupChoice>>({});
    const groups = groupBySection(questions).map((group, index) => {
        const key = `${group.heading}-${group.questions[0]?.id}`;

        return {
            ...group,
            key,
            changed: changedCount(group),
            open: groupIsOpen(choices[key], group, index),
        };
    });

    return {
        groups,
        choose: (key: string, changed: number, open: boolean) =>
            setChoices((current) => ({
                ...current,
                [key]: { open, changed },
            })),
        chooseAll: (open: boolean) =>
            setChoices(
                Object.fromEntries(
                    groups.map((group) => [
                        group.key,
                        { open, changed: group.changed },
                    ]),
                ),
            ),
    };
}
