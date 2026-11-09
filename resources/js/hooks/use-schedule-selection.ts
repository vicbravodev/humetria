import { useState } from 'react';
import type { SchedulePerson } from '@/types';

/**
 * Who RH ticked on this page of the schedules. Only the people the page shows count: going to another page, tab or search
 * leaves behind whoever is not on it, so what the bar says is what the next step will do.
 */
export function useScheduleSelection(people: readonly SchedulePerson[]) {
    const [ids, setIds] = useState<string[]>([]);
    const chosen = people.filter((person) => ids.includes(person.id));

    return {
        chosen,
        isChosen: (id: string) => ids.includes(id),
        toggle: (id: string, checked: boolean) =>
            setIds((current) =>
                checked
                    ? [...current.filter((other) => other !== id), id]
                    : current.filter((other) => other !== id),
            ),
        allChosen: people.length > 0 && chosen.length === people.length,
        toggleAll: (checked: boolean) =>
            setIds(checked ? people.map((person) => person.id) : []),
        clear: () => setIds([]),
    };
}

export type ScheduleSelection = ReturnType<typeof useScheduleSelection>;
