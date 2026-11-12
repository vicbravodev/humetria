import { ancestorsOf, frozen, parentsOf } from '@/lib/org-chart/chart-state';
import type { Branch, ChartState } from '@/lib/org-chart/chart-state';
import type { OrgNode } from '@/types';

/** Moves the people of a chain by a number of people: `direct` only for the first of them (the one that gains or loses a report). */
function resized(
    people: Map<string, OrgNode>,
    chain: string[],
    delta: number,
): void {
    chain.forEach((id, index) => {
        const person = people.get(id);

        if (person !== undefined) {
            people.set(id, {
                ...person,
                direct_reports:
                    person.direct_reports +
                    (index === 0 ? Math.sign(delta) : 0),
                total_reports: person.total_reports + delta,
            });
        }
    });
}

/** The ids with one more, by name, among those already there. */
function inserted(
    ids: string[],
    id: string,
    people: ReadonlyMap<string, OrgNode>,
): string[] {
    const name = people.get(id)?.name ?? '';
    const at = ids.findIndex(
        (other) =>
            (people.get(other)?.name ?? '').localeCompare(name, 'es') > 0,
    );

    return at === -1
        ? [...ids, id]
        : [...ids.slice(0, at), id, ...ids.slice(at)];
}

/** The branch of the new manager with the person in it; one that was not loaded starts with them, asking for the rest. */
function joined(
    branch: Branch | undefined,
    { id, manager }: { id: string; manager: string },
    people: ReadonlyMap<string, OrgNode>,
): Branch {
    if (branch !== undefined) {
        return { ...branch, ids: inserted(branch.ids, id, people) };
    }

    return {
        ids: [id],
        nextPage: (people.get(manager)?.direct_reports ?? 0) > 1 ? 1 : null,
        status: 'ready',
    };
}

/**
 * What the chart looks like once a person (with their whole team) reports to someone else, or to nobody: the person
 * leaves one branch and joins the other, and the teams of both chains change by the size of theirs. An undo is the
 * same move to the previous manager. A manager whose reports were not loaded starts their branch with the person and
 * asks for the rest when they are opened.
 */
export function moveInChart(
    state: ChartState,
    { node, to }: { node: OrgNode; to: string | null },
): ChartState {
    const parents = parentsOf(state);
    const from = parents.get(node.id) ?? null;

    if (from === to && parents.has(node.id)) {
        return state;
    }

    const known = state.people.get(node.id) ?? node;
    const people = new Map(state.people).set(node.id, {
        ...known,
        manager_inactive: false,
    });
    const size = known.total_reports + 1;
    const branches = new Map<string, Branch>(state.branches);

    if (from !== null) {
        resized(
            people,
            [from, ...ancestorsOf(state, from).toReversed()],
            -size,
        );
        const branch = branches.get(from);

        if (branch !== undefined) {
            branches.set(from, {
                ...branch,
                ids: branch.ids.filter((id) => id !== node.id),
            });
        }
    }

    const expanded = new Set(frozen(state).expanded);
    const roots = state.roots.filter((id) => id !== node.id);

    if (to === null) {
        roots.push(node.id);
    } else {
        resized(people, [to, ...ancestorsOf(state, to).toReversed()], size);
        branches.set(
            to,
            joined(branches.get(to), { id: node.id, manager: to }, people),
        );

        expanded.add(to);
    }

    return { people, roots, branches, expanded, levels: null };
}
