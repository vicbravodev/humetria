import { ancestorsOf, teamIds } from '@/lib/org-chart/chart-state';
import type { Branch, ChartState } from '@/lib/org-chart/chart-state';
import type { OrgNode } from '@/types';

/** The card that stands for the whole company when several people have nobody above them. */
export const COMPANY_ID = 'company';

export const moreId = (parentId: string) => `more-${parentId}`;

export const CARD_WIDTH = 216;
export const CARD_HEIGHT = 72;
const MORE_HEIGHT = 44;

export type Tone = 'normal' | 'selected' | 'path' | 'dimmed';

export type ChartNodeData =
    | {
          kind: 'person';
          node: OrgNode;
          /** Whether their reports are on screen; undefined for someone who leads nobody. */
          open: boolean | undefined;
          tone: Tone;
          /** Worth a second look: more than 15 direct reports, or a manager who left. */
          attention: boolean;
          status: Branch['status'] | undefined;
      }
    | { kind: 'company'; name: string; people: number }
    | {
          kind: 'more';
          parentId: string;
          /** The reports still to load; null when a filter hides some of them. */
          left: number | null;
          status: Branch['status'];
      };

/** A card of a person, with whether their reports are open and how they are lit. */
export type PersonData = Extract<ChartNodeData, { kind: 'person' }>;

export type ViewNode = {
    id: string;
    width: number;
    height: number;
    data: ChartNodeData;
    /** The manager whose column of people who lead nobody this card hangs in; null for a card laid out as a tree. */
    stack: string | null;
};

export type ViewEdge = {
    id: string;
    source: string;
    target: string;
    highlighted: boolean;
    /** The edge goes to a card of a column: it leaves the manager at the left and enters the card from its left. */
    stacked: boolean;
};

export type ViewOptions = {
    company: string;
    /** Active people of the whole company. */
    people: number;
    selected: string | null;
    /** The person a search points at: their way from the top is lit, the rest dims. */
    highlight: string | null;
    filtered?: boolean;
};

type Lit = { path: ReadonlySet<string>; team: ReadonlySet<string> };

function litBy(state: ChartState, highlight: string | null): Lit | null {
    return highlight === null
        ? null
        : {
              path: new Set([...ancestorsOf(state, highlight), highlight]),
              team: new Set(teamIds(state, highlight)),
          };
}

function toneOf(
    node: OrgNode,
    { selected }: ViewOptions,
    lit: Lit | null,
): Tone {
    if (
        node.id === selected ||
        (lit !== null && lit.path.has(node.id) && lit.team.has(node.id))
    ) {
        return 'selected';
    }

    if (lit?.path.has(node.id) === true) {
        return 'path';
    }

    return !node.matches || (lit !== null && !lit.team.has(node.id))
        ? 'dimmed'
        : 'normal';
}

type Graph = { nodes: ViewNode[]; edges: ViewEdge[] };
type Context = {
    state: ChartState;
    options: ViewOptions;
    lit: Lit | null;
    graph: Graph;
    seen: Set<string>;
};

function personView(
    person: OrgNode,
    stack: string | null,
    { state, options, lit }: Context,
): ViewNode {
    const branch = state.branches.get(person.id);
    const leads = person.direct_reports > 0 || (branch?.ids.length ?? 0) > 0;

    return {
        id: person.id,
        width: CARD_WIDTH,
        height: CARD_HEIGHT,
        stack,
        data: {
            kind: 'person',
            node: person,
            open: leads ? state.expanded.has(person.id) : undefined,
            tone: toneOf(person, options, lit),
            attention: person.flag?.value === 'many' || person.manager_inactive,
            status: branch?.status,
        },
    };
}

function moreView(
    person: OrgNode,
    branch: Branch,
    { options, stacked }: Context & { stacked: boolean },
): ViewNode {
    const left = person.direct_reports - branch.ids.length;

    return {
        id: moreId(person.id),
        width: CARD_WIDTH,
        height: MORE_HEIGHT,
        stack: stacked ? person.id : null,
        data: {
            kind: 'more',
            parentId: person.id,
            left: options.filtered === true || left <= 0 ? null : left,
            status: branch.status,
        },
    };
}

function link(
    { graph, lit }: Context,
    [source, target]: [string, string],
    stacked = false,
): void {
    graph.edges.push({
        id: `${source}>${target}`,
        source,
        target,
        stacked,
        highlighted:
            lit !== null &&
            lit.path.has(target) &&
            (source === COMPANY_ID || lit.path.has(source)),
    });
}

/** Two or more people who lead nobody hang in a column under their manager: a tree a company of hundreds can read. */
function columnOf(state: ChartState, branch: Branch): boolean {
    return (
        branch.ids.length >= 2 &&
        branch.ids.every(
            (child) => (state.people.get(child)?.direct_reports ?? 0) === 0,
        )
    );
}

/** A person, and below them whoever is open, in reading order; a branch with more pages ends in a card that asks for them. */
function visit(
    context: Context,
    id: string,
    stack: string | null = null,
): void {
    const person = context.state.people.get(id);

    if (person === undefined || context.seen.has(id)) {
        return;
    }

    context.seen.add(id);
    const view = personView(person, stack, context);
    const branch = context.state.branches.get(id);
    context.graph.nodes.push(view);

    if (
        view.data.kind !== 'person' ||
        view.data.open !== true ||
        branch === undefined
    ) {
        return;
    }

    const stacked = columnOf(context.state, branch);

    for (const child of branch.ids) {
        link(context, [id, child], stacked);
        visit(context, child, stacked ? id : null);
    }

    if (branch.nextPage !== null) {
        context.graph.nodes.push(
            moreView(person, branch, { ...context, stacked }),
        );
        link({ ...context, lit: null }, [id, moreId(id)], stacked);
    }
}

/**
 * What the chart draws: the heads (whoever has nobody above and leads people; whoever leads nobody is in the tray and
 * the list), a company card over them when there are several, and every open person with their reports loaded.
 */
export function chartGraph(state: ChartState, options: ViewOptions): Graph {
    const graph: Graph = { nodes: [], edges: [] };
    const context: Context = {
        state,
        options,
        lit: litBy(state, options.highlight),
        graph,
        seen: new Set<string>(),
    };
    const heads = state.roots.filter(
        (id) => (state.people.get(id)?.direct_reports ?? 0) > 0,
    );
    const several = heads.length > 1;

    if (several) {
        graph.nodes.push({
            id: COMPANY_ID,
            width: CARD_WIDTH,
            height: CARD_HEIGHT,
            stack: null,
            data: {
                kind: 'company',
                name: options.company,
                people: options.people,
            },
        });
    }

    for (const head of heads) {
        if (several) {
            link(context, [COMPANY_ID, head]);
        }

        visit(context, head);
    }

    return graph;
}
