import { useState } from 'react';

/**
 * Who RH ticked on this page. Only the ids the page shows count: going to another page, tab or search leaves behind
 * whoever is not on it, so what the bar says is what the next step will do.
 */
export function useIdSelection(shown: readonly string[]) {
    const [ids, setIds] = useState<string[]>([]);
    const chosen = shown.filter((id) => ids.includes(id));

    return {
        chosen,
        isChosen: (id: string) => ids.includes(id),
        toggle: (id: string, checked: boolean) =>
            setIds((current) =>
                checked
                    ? [...current.filter((other) => other !== id), id]
                    : current.filter((other) => other !== id),
            ),
        allChosen: shown.length > 0 && chosen.length === shown.length,
        toggleAll: (checked: boolean) => setIds(checked ? [...shown] : []),
        clear: () => setIds([]),
    };
}

export type IdSelection = ReturnType<typeof useIdSelection>;
