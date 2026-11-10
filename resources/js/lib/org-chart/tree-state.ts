import type { OrgNode, Paginated } from '@/types';

/** The reports of a person loaded so far, and the page that follows them. */
export type Branch = {
    nodes: OrgNode[];
    nextPage: number | null;
    status: 'loading' | 'ready' | 'error';
};

/** What the tree holds beyond its roots: who is open, and the reports loaded for each person. */
export type TreeState = {
    expanded: ReadonlySet<string>;
    children: ReadonlyMap<string, Branch>;
};

export type TreeAction =
    | { type: 'toggle'; id: string }
    | { type: 'loading'; id: string }
    | { type: 'loaded'; id: string; nodes: OrgNode[]; nextPage: number | null }
    | { type: 'failed'; id: string };

export const emptyTree: TreeState = {
    expanded: new Set(),
    children: new Map(),
};

const NO_BRANCH: Branch = { nodes: [], nextPage: null, status: 'ready' };

function withBranch(state: TreeState, id: string, branch: Branch): TreeState {
    return { ...state, children: new Map(state.children).set(id, branch) };
}

function toggled(expanded: ReadonlySet<string>, id: string): Set<string> {
    const next = new Set(expanded);

    if (!next.delete(id)) {
        next.add(id);
    }

    return next;
}

export function reduce(state: TreeState, action: TreeAction): TreeState {
    const branch = state.children.get(action.id) ?? NO_BRANCH;

    switch (action.type) {
        case 'toggle':
            return { ...state, expanded: toggled(state.expanded, action.id) };
        case 'loading':
            return withBranch(state, action.id, {
                ...branch,
                status: 'loading',
            });
        case 'failed':
            return withBranch(state, action.id, { ...branch, status: 'error' });
        case 'loaded': {
            const known = new Set(branch.nodes.map((node) => node.id));

            return withBranch(state, action.id, {
                nodes: [
                    ...branch.nodes,
                    ...action.nodes.filter((node) => !known.has(node.id)),
                ],
                nextPage: action.nextPage,
                status: 'ready',
            });
        }
    }
}

/** Each person on screen with who they hang from, in reading order. */
function walk(
    nodes: OrgNode[],
    parent: string | null,
    state: TreeState,
): [string, string | null][] {
    return nodes.flatMap((node) => [
        [node.id, parent] satisfies [string, string | null],
        ...(state.expanded.has(node.id)
            ? walk(state.children.get(node.id)?.nodes ?? [], node.id, state)
            : []),
    ]);
}

/** The people on screen, in reading order: the reports of a closed person are left out. */
export function visibleIds(roots: OrgNode[], state: TreeState): string[] {
    return walk(roots, null, state).map(([id]) => id);
}

/** Who each loaded person reports to, open or not; null for a root. */
export function parentsOf(
    roots: OrgNode[],
    state: TreeState,
): ReadonlyMap<string, string | null> {
    const everyone: TreeState = {
        ...state,
        expanded: new Set(state.children.keys()),
    };

    return new Map(walk(roots, null, everyone));
}

/**
 * The tree of a focused person: the chain from the top as a path of one report per level, each open, down to the
 * person's own reports. Whoever has more reports than the one on the path offers them from their first page.
 */
export function focusedTree(
    chain: OrgNode[],
    reports: Paginated<OrgNode> | null,
): TreeState {
    const children = new Map<string, Branch>();
    const expanded = new Set<string>();

    chain.forEach((node, index) => {
        const next = chain[index + 1];

        if (next !== undefined) {
            const shown = next.active ? 1 : 0;

            children.set(node.id, {
                nodes: [next],
                nextPage: node.direct_reports > shown ? 1 : null,
                status: 'ready',
            });
            expanded.add(node.id);
        } else if (reports !== null) {
            children.set(node.id, {
                nodes: reports.data,
                nextPage:
                    reports.current_page < reports.last_page
                        ? reports.current_page + 1
                        : null,
                status: 'ready',
            });

            if (reports.data.length > 0) {
                expanded.add(node.id);
            }
        }
    });

    return { expanded, children };
}

const TEXTS = ['id', 'name', 'job_title', 'area', 'work_center'];
const FLAGS = ['active', 'matches', 'manager_inactive'];
const COUNTS = ['direct_reports', 'total_reports'];

function isFlag(value: unknown): value is OrgNode['flag'] {
    return (
        value === null ||
        (typeof value === 'object' &&
            'value' in value &&
            (value.value === 'few' || value.value === 'many') &&
            'label' in value &&
            typeof value.label === 'string')
    );
}

function isOrgNode(value: unknown): value is OrgNode {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const fields = new Map<string, unknown>(Object.entries(value));
    const all = (keys: string[], type: string) =>
        keys.every((key) => typeof fields.get(key) === type);

    return (
        all(TEXTS, 'string') &&
        all(FLAGS, 'boolean') &&
        all(COUNTS, 'number') &&
        fields.has('flag') &&
        isFlag(fields.get('flag'))
    );
}

/** A page of reports as the server answers it: only what has the shape of a person is kept; null when it is no page. */
export function reportsPage(
    body: unknown,
): { nodes: OrgNode[]; nextPage: number | null } | null {
    if (
        typeof body !== 'object' ||
        body === null ||
        !('data' in body) ||
        !Array.isArray(body.data) ||
        !('current_page' in body) ||
        typeof body.current_page !== 'number' ||
        !('last_page' in body) ||
        typeof body.last_page !== 'number'
    ) {
        return null;
    }

    return {
        nodes: body.data.filter(isOrgNode),
        nextPage:
            body.current_page < body.last_page ? body.current_page + 1 : null,
    };
}
