import { parentsOf } from '@/lib/org-chart/chart-state';
import type { ChartState } from '@/lib/org-chart/chart-state';
import type { OrgNode } from '@/types';

const PREVIEW = 5;

/** What the panel of a person reads from the chart: them, their manager and the reports loaded so far. */
export function panelOf(
    state: ChartState,
    id: string,
): {
    node: OrgNode;
    manager: OrgNode | null;
    reports: OrgNode[];
    /** Whether more of their team is still to be loaded. */
    more: boolean;
    status: 'loading' | 'ready' | 'error' | undefined;
} | null {
    const node = state.people.get(id);

    if (node === undefined) {
        return null;
    }

    const parent = parentsOf(state).get(id) ?? null;
    const branch = state.branches.get(id);

    return {
        node,
        manager: parent === null ? null : (state.people.get(parent) ?? null),
        reports: (branch?.ids ?? []).flatMap((reportId) => {
            const report = state.people.get(reportId);

            return report === undefined ? [] : [report];
        }),
        more: branch?.nextPage != null,
        status: branch?.status,
    };
}

/** The first people of a team, and the link that shows them all when there are more than a few. */
export function teamPreview(
    reports: OrgNode[],
    directReports: number,
    all: boolean,
): { shown: OrgNode[]; seeAll: string | null } {
    if (all || directReports <= PREVIEW) {
        return { shown: reports, seeAll: null };
    }

    return {
        shown: reports.slice(0, PREVIEW),
        seeAll: `Ver los ${directReports}`,
    };
}

/** The name in an option «Nombre · Puesto», as the server labels a person. */
export function managerName(label: string): string {
    return label.split(' · ')[0] ?? label;
}

/** Whom an option of the manager field stands for: nobody for «Sin jefe directo» (its value is empty). */
export function managerOf(option: {
    value: string;
    label: string;
}): { id: string; name: string } | null {
    return option.value === ''
        ? null
        : { id: option.value, name: managerName(option.label) };
}
