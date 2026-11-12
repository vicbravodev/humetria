import { Graph, layout } from '@dagrejs/dagre';
import type { ViewEdge, ViewNode } from '@/lib/org-chart/chart-view';

/** Space between one row of cards and the next: the «+4 · 30 en total» pill and the elbow of the connector live in it. */
export const RANK_GAP = 72;
/** Space between cards of the same row. */
export const SIBLING_GAP = 28;

/** Where the top-left corner of each card goes, as dagre places the tree top-down with its manager over their reports. */
export function layoutGraph(
    nodes: ViewNode[],
    edges: ViewEdge[],
): Map<string, { x: number; y: number }> {
    const graph = new Graph();

    graph.setGraph({
        rankdir: 'TB',
        nodesep: SIBLING_GAP,
        ranksep: RANK_GAP,
    });
    graph.setDefaultEdgeLabel(() => ({}));
    nodes.forEach(({ id, width, height }) =>
        graph.setNode(id, { width, height }),
    );
    edges.forEach(({ source, target }) => graph.setEdge(source, target));
    layout(graph);

    return new Map(
        nodes.map(({ id, width, height }) => {
            const placed = graph.node(id);

            return [id, { x: placed.x - width / 2, y: placed.y - height / 2 }];
        }),
    );
}
