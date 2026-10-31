import { router, useRemember } from '@inertiajs/react';
import { chooseAll, chosenNames, idList, toggleChosen } from '@/lib/selection';

/**
 * Who was ticked in a work center's list of people. It stays across pages and searches (and coming back), and can take
 * at once everyone the search found, on every page, asked of the server. The names seen are kept to show the chosen
 * as chips; those taken at once without being seen are counted. `preselected` is who a traumatic-event notice named: it
 * starts ticked.
 */
export function useChosenPeople(
    workCenterId: string,
    preselected: { id: string; name: string } | null = null,
) {
    const [chosen, setChosen] = useRemember<string[]>(
        preselected === null ? [] : [preselected.id],
        `trauma-chosen-${workCenterId}`,
    );
    const [names, setNames] = useRemember<Record<string, string>>(
        preselected === null ? {} : { [preselected.id]: preselected.name },
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
