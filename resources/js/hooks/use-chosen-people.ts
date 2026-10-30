import { router, useRemember } from '@inertiajs/react';
import { chooseAll, idList, toggleChosen } from '@/lib/selection';

/**
 * Who was ticked in a work center's list of people. It stays across pages and searches (and coming back), and can take
 * at once everyone the search found, on every page, asked of the server.
 */
export function useChosenPeople(workCenterId: string) {
    const [chosen, setChosen] = useRemember<string[]>(
        [],
        `trauma-chosen-${workCenterId}`,
    );

    return {
        chosen,
        toggle: (id: string, checked: boolean) =>
            setChosen((current) => toggleChosen(current, id, checked)),
        chooseFound: () =>
            router.reload({
                only: ['matching'],
                onSuccess: (page) =>
                    setChosen((current) =>
                        chooseAll(current, idList(page.props['matching'])),
                    ),
            }),
        clear: () => setChosen([]),
    };
}
