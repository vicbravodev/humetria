import { useState } from 'react';

/**
 * The marks chosen in a page of the queue to verify together. What was chosen on another page, or is no longer in this
 * one (it was verified), is not carried along.
 */
export function useOfflineSelection(onPage: string[]) {
    const [chosen, setChosen] = useState<string[]>([]);
    const ids = chosen.filter((id) => onPage.includes(id));

    return {
        ids,
        all: ids.length === onPage.length,
        toggle: (id: string, checked: boolean) =>
            setChosen(
                checked ? [...ids, id] : ids.filter((each) => each !== id),
            ),
        toggleAll: (checked: boolean) => setChosen(checked ? onPage : []),
        clear: () => setChosen([]),
    };
}
