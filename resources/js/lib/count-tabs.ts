type Splittable = {
    count?: number | undefined;
    active: boolean;
    needsAction?: boolean | undefined;
};

/**
 * The tabs of a list by what asks for action: those first (order kept inside each group), and a view that holds none
 * left out of the row, into `hidden` ("Otros filtros"). The open view always stays in the row.
 */
export function splitByAction<T extends Splittable>(
    tabs: T[],
): { shown: T[]; hidden: T[] } {
    const empty = (tab: T): boolean => tab.count === 0 && !tab.active;
    const kept = tabs.filter((tab) => !empty(tab));

    return {
        shown: [
            ...kept.filter((tab) => tab.needsAction === true),
            ...kept.filter((tab) => tab.needsAction !== true),
        ],
        hidden: tabs.filter(empty),
    };
}
