import type { Edge, Node } from '@xyflow/react';
import type { DropTone } from '@/lib/org-chart/card-style';
import type {
    PersonData,
    ViewEdge,
    ViewNode,
} from '@/lib/org-chart/chart-view';

export type PersonFlowNode = Node<
    {
        view: PersonData;
        drop: DropTone | null;
        /** Hangs in a column under its manager: its connector enters from the left. */
        stacked: boolean;
    },
    'person'
>;
export type CompanyFlowNode = Node<{ name: string; people: number }, 'company'>;
export type MoreFlowNode = Node<
    {
        parentId: string;
        left: number | null;
        status: 'loading' | 'ready' | 'error';
        stacked: boolean;
    },
    'more'
>;
/** Where to take the view: the people to bring into view (everybody when `ids` is null). A new request is a new object. */
export type CameraRequest = {
    ids: string[] | null;
    /** On first opening: the root and its first level, where to look when the whole chart would be too small to read. */
    readable?: string[];
} | null;

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
    const place = {
        id: view.id,
        position: at,
        // The size is known (the cards are fixed): a card handed over again is not measured again.
        measured: { width: view.width, height: view.height },
        ...FLAGS,
    };
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
                    stacked: view.stack !== null,
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
                    stacked: view.stack !== null,
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
        edges: graph.edges.map(
            ({ id, source, target, highlighted, stacked }) => ({
                id,
                source,
                target,
                // A column leaves its manager at the left, where the rail runs.
                sourceHandle: stacked ? 'stack' : null,
                targetHandle: null,
                type: 'smoothstep',
                pathOptions: { borderRadius: 10 },
                focusable: false,
                selectable: false,
                // Connectors say nothing a reader needs, and React Flow's own label is English and made of ids.
                domAttributes: { 'aria-label': undefined, 'aria-hidden': true },
                style: {
                    stroke: highlighted ? 'var(--primary)' : 'var(--input)',
                    strokeWidth: highlighted ? 2 : 1.5,
                },
            }),
        ),
    };
}

/**
 * The cards while one is being dragged: the dragged one where the pointer has it and the one under it saying what a drop
 * would do. Every other card is the very same object, so the canvas touches only those two on each move.
 */
export function applyDrag(
    nodes: FlowNode[],
    where: {
        dragging: { id: string; x: number; y: number } | null;
        drop: { id: string; tone: DropTone } | null;
    },
): FlowNode[] {
    if (where.dragging === null && where.drop === null) {
        return nodes;
    }

    return nodes.map((node) => {
        if (where.dragging?.id === node.id) {
            return {
                ...node,
                position: { x: where.dragging.x, y: where.dragging.y },
            };
        }

        return where.drop?.id === node.id && node.type === 'person'
            ? { ...node, data: { ...node.data, drop: where.drop.tone } }
            : node;
    });
}
