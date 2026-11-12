import type { Edge, Node } from '@xyflow/react';
import type { DropTone } from '@/lib/org-chart/card-style';
import type {
    PersonData,
    ViewEdge,
    ViewNode,
} from '@/lib/org-chart/chart-view';

export type PersonFlowNode = Node<
    { view: PersonData; drop: DropTone | null },
    'person'
>;
export type CompanyFlowNode = Node<{ name: string; people: number }, 'company'>;
export type MoreFlowNode = Node<
    {
        parentId: string;
        left: number | null;
        status: 'loading' | 'ready' | 'error';
    },
    'more'
>;
export type FlowNode = PersonFlowNode | CompanyFlowNode | MoreFlowNode;

const FLAGS: { selectable: false; focusable: false; connectable: false } = {
    selectable: false,
    focusable: false,
    connectable: false,
};

function flowNode(
    view: ViewNode,
    at: { x: number; y: number },
    drop: { id: string; tone: DropTone } | null,
): FlowNode {
    const place = { id: view.id, position: at, ...FLAGS };
    const { data } = view;

    switch (data.kind) {
        case 'person':
            return {
                ...place,
                type: 'person',
                draggable: true,
                data: {
                    view: data,
                    drop: drop?.id === view.id ? drop.tone : null,
                },
            };
        case 'company':
            return {
                ...place,
                type: 'company',
                draggable: false,
                data: { name: data.name, people: data.people },
            };
        case 'more':
            return {
                ...place,
                type: 'more',
                draggable: false,
                data: {
                    parentId: data.parentId,
                    left: data.left,
                    status: data.status,
                },
            };
    }
}

/** The graph in the shape React Flow reads: each card where the layout put it (or where it is being dragged), each edge an elbow. */
export function toFlow(
    graph: { nodes: ViewNode[]; edges: ViewEdge[] },
    where: {
        positions: ReadonlyMap<string, { x: number; y: number }>;
        dragging: { id: string; x: number; y: number } | null;
        drop: { id: string; tone: DropTone } | null;
    },
): { nodes: FlowNode[]; edges: Edge[] } {
    return {
        nodes: graph.nodes.map((view) =>
            flowNode(
                view,
                where.dragging?.id === view.id
                    ? { x: where.dragging.x, y: where.dragging.y }
                    : (where.positions.get(view.id) ?? { x: 0, y: 0 }),
                where.drop,
            ),
        ),
        edges: graph.edges.map(({ id, source, target, highlighted }) => ({
            id,
            source,
            target,
            type: 'smoothstep',
            pathOptions: { borderRadius: 10 },
            focusable: false,
            selectable: false,
            style: {
                stroke: highlighted ? 'var(--primary)' : 'var(--input)',
                strokeWidth: highlighted ? 2 : 1.5,
            },
        })),
    };
}
