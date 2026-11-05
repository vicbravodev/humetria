import { useState } from 'react';
import { toggleChosen } from '@/lib/selection';

/** The most scenarios the comparison holds, and the fewest it makes sense for. */
export const MOST_COMPARED = 4;
export const LEAST_COMPARED = 2;

/** The scenarios ticked in the list to compare: at most four, and the comparison opens from two. */
export function useScenarioSelection() {
    const [chosen, setChosen] = useState<string[]>([]);

    return {
        chosen,
        full: chosen.length >= MOST_COMPARED,
        ready: chosen.length >= LEAST_COMPARED,
        toggle: (id: string, checked: boolean) =>
            setChosen((current) => toggleChosen(current, id, checked)),
    };
}
