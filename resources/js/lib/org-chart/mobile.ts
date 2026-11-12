import { ancestorsOf } from '@/lib/org-chart/chart-state';
import type { ChartState } from '@/lib/org-chart/chart-state';
import type { OrgNode } from '@/types';

export type Crumb = { id: string | null; label: string };

/** Whoever has nobody above and leads people: the top of the chart. Whoever leads nobody is in the list, not here. */
export function headsOf(state: ChartState): OrgNode[] {
    return state.roots.flatMap((id) => {
        const person = state.people.get(id);

        return person !== undefined && person.direct_reports > 0
            ? [person]
            : [];
    });
}

/** Where the phone starts: on the person a search found, or on the only head; at the company when there are several. */
export function startAt(
    state: ChartState,
    focus: string | null,
): string | null {
    if (focus !== null) {
        return focus;
    }

    const heads = headsOf(state);

    return heads.length === 1 ? (heads[0]?.id ?? null) : null;
}

/** The way from the company down to the person, each one a step back; the person is the last. */
export function crumbsOf(
    state: ChartState,
    current: string | null,
    company: string,
): Crumb[] {
    const person = current === null ? undefined : state.people.get(current);

    if (current === null || person === undefined) {
        return [{ id: null, label: company }];
    }

    return [
        { id: null, label: company },
        ...[...ancestorsOf(state, current), current].map((id) => ({
            id,
            label: state.people.get(id)?.name ?? '',
        })),
    ];
}
