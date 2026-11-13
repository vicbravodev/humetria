import type { OrgNode } from '@/types';

/** The reports of a person loaded so far, and the page that follows them. */
export type Branch = {
    ids: string[];
    nextPage: number | null;
    status: 'loading' | 'ready' | 'error';
};

/**
 * What the chart knows: every person loaded, who leads at the top, the reports loaded of each person and who is open.
 * People are kept apart from the branches so that moving one person changes two branches and nothing else.
 */
export type ChartState = {
    people: ReadonlyMap<string, OrgNode>;
    roots: readonly string[];
    branches: ReadonlyMap<string, Branch>;
    expanded: ReadonlySet<string>;
    /** How many levels «Mostrar» keeps open, whatever is loaded; null once a branch was opened or closed by hand. */
    levels: number | null;
};

export type ChartAction =
    | { type: 'toggle'; id: string }
    | { type: 'loading'; id: string }
    | { type: 'loaded'; id: string; nodes: OrgNode[]; nextPage: number | null }
    | { type: 'failed'; id: string }
    | { type: 'expand'; ids: string[] }
    | { type: 'levels'; levels: number }
    | { type: 'replace'; state: ChartState };

/** A page of reports as the server sends it, with what the chart needs of it. */
type ReportsPage = { data: OrgNode[]; current_page: number; last_page: number };

export type ChartInit = {
    roots: OrgNode[];
    /** The first page of the reports of each root, keyed by the root. */
    firstLevel: Record<string, OrgNode[]>;
    /** From the top down to the focused person, who comes last; empty without a focus. */
    chain: OrgNode[];
    /** The reports of the focused person. */
    focusChildren: ReportsPage | null;
};

const NO_BRANCH: Branch = { ids: [], nextPage: null, status: 'ready' };

function withBranch(state: ChartState, id: string, branch: Branch): ChartState {
    return { ...state, branches: new Map(state.branches).set(id, branch) };
}

function toggled(expanded: ReadonlySet<string>, id: string): Set<string> {
    const next = new Set(expanded);

    if (!next.delete(id)) {
        next.add(id);
    }

    return next;
}

/** The branch of a person on the way to the focus: what was loaded, with the next one of the path among it. */
function pathBranch(
    person: OrgNode,
    next: OrgNode,
    loaded: Branch | undefined,
): Branch {
    const known = loaded?.ids ?? [];
    const ids =
        next.active && !known.includes(next.id) ? [...known, next.id] : known;

    return {
        ids,
        nextPage:
            loaded?.nextPage ?? (person.direct_reports > ids.length ? 1 : null),
        status: 'ready',
    };
}

/** The branch of the focused person: their own reports from their first page. */
function focusBranch(
    reports: ReportsPage | null,
    people: Map<string, OrgNode>,
): Branch | null {
    if (reports === null) {
        return null;
    }

    reports.data.forEach((child) => people.set(child.id, child));

    return {
        ids: reports.data.map((child) => child.id),
        nextPage:
            reports.current_page < reports.last_page
                ? reports.current_page + 1
                : null,
        status: 'ready',
    };
}

/** What is drawn when the page arrives: the roots with their first level and, with a focus, the way down to it. */
export function initialChart({
    roots,
    firstLevel,
    chain,
    focusChildren,
}: ChartInit): ChartState {
    const people = new Map<string, OrgNode>();
    const branches = new Map<string, Branch>();
    const expanded = new Set<string>();
    const top = chain[0];
    const heads =
        top === undefined || roots.some(({ id }) => id === top.id)
            ? roots
            : [top, ...roots];

    [...heads, ...chain].forEach((person) => people.set(person.id, person));

    for (const [id, nodes] of Object.entries(firstLevel)) {
        nodes.forEach((person) => people.set(person.id, person));
        branches.set(id, {
            ids: nodes.map((person) => person.id),
            nextPage:
                (people.get(id)?.direct_reports ?? 0) > nodes.length ? 2 : null,
            status: 'ready',
        });

        if (nodes.length > 0) {
            expanded.add(id);
        }
    }

    chain.forEach((person, index) => {
        const next = chain[index + 1];
        const branch =
            next === undefined
                ? focusBranch(focusChildren, people)
                : pathBranch(person, next, branches.get(person.id));

        if (branch !== null) {
            branches.set(person.id, branch);

            if (branch.ids.length > 0 || next !== undefined) {
                expanded.add(person.id);
            }
        }
    });

    return {
        people,
        roots: heads.map(({ id }) => id),
        branches,
        expanded,
        levels: chain.length > 0 ? null : 2,
    };
}

export function reduceChart(
    state: ChartState,
    action: ChartAction,
): ChartState {
    if (action.type === 'replace') {
        return action.state;
    }

    if (action.type === 'expand') {
        return { ...state, expanded: new Set(action.ids), levels: null };
    }

    if (action.type === 'levels') {
        return { ...state, levels: action.levels };
    }

    const branch = state.branches.get(action.id) ?? NO_BRANCH;

    switch (action.type) {
        case 'toggle':
            return {
                ...frozen(state),
                expanded: toggled(frozen(state).expanded, action.id),
            };
        case 'loading':
            return withBranch(state, action.id, {
                ...branch,
                status: 'loading',
            });
        case 'failed':
            return withBranch(state, action.id, { ...branch, status: 'error' });
        case 'loaded': {
            const people = new Map(state.people);
            action.nodes.forEach((person) => people.set(person.id, person));
            const known = new Set(branch.ids);

            return withBranch({ ...state, people }, action.id, {
                ids: [
                    ...branch.ids,
                    ...action.nodes
                        .map(({ id }) => id)
                        .filter((id) => !known.has(id)),
                ],
                nextPage: action.nextPage,
                status: 'ready',
            });
        }
    }
}

/** Who each loaded person reports to; null for a root. */
export function parentsOf(
    state: ChartState,
): ReadonlyMap<string, string | null> {
    const parents = new Map<string, string | null>(
        state.roots.map((id) => [id, null]),
    );

    for (const [parent, branch] of state.branches) {
        branch.ids.forEach((id) => parents.set(id, parent));
    }

    return parents;
}

/** The people above one, from the top down; it stops on a loop already in the data. */
export function ancestorsOf(state: ChartState, id: string): string[] {
    const parents = parentsOf(state);
    const above: string[] = [];
    let current = parents.get(id) ?? null;

    while (current !== null && !above.includes(current)) {
        above.unshift(current);
        current = parents.get(current) ?? null;
    }

    return above;
}

/** A person with everybody loaded below them. */
export function teamIds(state: ChartState, id: string): string[] {
    const found = new Set<string>([id]);
    const queue = [id];

    for (let next = queue.shift(); next !== undefined; next = queue.shift()) {
        for (const child of state.branches.get(next)?.ids ?? []) {
            if (!found.has(child)) {
                found.add(child);
                queue.push(child);
            }
        }
    }

    return [...found];
}

/** A team with more direct reports than this waits behind its pill when the levels are asked: only «Todos» opens it. */
export const LARGE_TEAM = 15;

/** Whoever leads people and sits above the level asked (the roots are the first level): who «Mostrar: N niveles» opens. */
export function levelsToExpand(state: ChartState, levels: number): string[] {
    const open: string[] = [];
    const seen = new Set<string>();
    const opens = (id: string): boolean => {
        const reports = state.people.get(id)?.direct_reports ?? 0;

        return reports > 0 && (reports <= LARGE_TEAM || levels === Infinity);
    };
    let row = [...state.roots];

    for (let level = 1; level < levels && row.length > 0; level += 1) {
        const leaders = row.filter((id) => !seen.has(id) && seen.add(id));
        const opened = leaders.filter(opens);

        open.push(...opened);
        row = opened.flatMap((id) => state.branches.get(id)?.ids ?? []);
    }

    return open;
}

/** Whoever is open and leads people but has no reports loaded, nor on their way: the ones to ask the server for. */
export function missingBranches(state: ChartState): string[] {
    return [...state.expanded].filter(
        (id) =>
            (state.people.get(id)?.direct_reports ?? 0) > 0 &&
            !state.branches.has(id),
    );
}

/** What «Mostrar: N niveles» keeps open, as the open people of the state; the same state when it was opened by hand. */
export function shown(state: ChartState): ChartState {
    return state.levels === null
        ? state
        : { ...state, expanded: new Set(levelsToExpand(state, state.levels)) };
}

/** The state with the open people written down and no levels: what opening or closing one by hand starts from. */
export function frozen(state: ChartState): ChartState {
    return { ...shown(state), levels: null };
}
