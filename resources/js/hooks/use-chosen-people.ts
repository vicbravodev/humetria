import { router, useRemember } from '@inertiajs/react';
import { chooseAll, chosenNames, idList, toggleChosen } from '@/lib/selection';

/**
 * Who was ticked in a work center's list of people. It stays across pages and searches (and coming back), and can take
 * at once everyone the search found, on every page, asked of the server. The names seen are kept to show the chosen
 * as chips; those taken at once without being seen are counted.
 */
export function useChosenPeople(workCenterId: string) {
    const [chosen, setChosen] = useRemember<string[]>(
        [],
        `trauma-chosen-${workCenterId}`,
    );
    const [names, setNames] = useRemember<Record<string, string>>(
        {},
        `trauma-names-${workCenterId}`,
    );

    return {
        chosen,
        chips: chosenNames(chosen, names),
        toggle: (person: { id: string; name: string }, checked: boolean) => {
            setNames((current) => ({ ...current, [person.id]: person.name }));
            setChosen((current) => toggleChosen(current, person.id, checked));
        },
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
