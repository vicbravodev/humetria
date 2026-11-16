export type PanelListFilters = {
    q: string;
    status: string;
    /** The center the list narrows to; empty for every center. */
    center: string;
};

/** The address of a list of the panel by person (accesses, schedules) as the server reads it: only what differs from the default view. */
export function panelListQuery(
    filters: PanelListFilters,
): Partial<Record<keyof PanelListFilters, string>> {
    const q = filters.q.trim();

    return {
        ...(q === '' ? {} : { q }),
        ...(filters.status === 'all' ? {} : { status: filters.status }),
        ...(filters.center === '' ? {} : { center: filters.center }),
    };
}
