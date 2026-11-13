import { Graph, layout } from '@dagrejs/dagre';
import type { ViewEdge, ViewNode } from '@/lib/org-chart/chart-view';

/** Space between one row of cards and the next: the «+4 · 30 en total» pill and the elbow of the connector live in it. */
export const RANK_GAP = 72;
/** Space between cards of the same row. */
export const SIBLING_GAP = 20;

/** How far a column of people who lead nobody hangs to the right of their manager's left edge. */
export const STACK_INDENT = 40;
/** Space between the cards of a column. */
export const STACK_GAP = 12;
/** What the layout reserves on each side of a column, so the columns of two neighbours never touch. */
const STACK_MARGIN = 40;

type Corner = { x: number; y: number };

/** The height of a column of cards, one under the other. */
const columnHeight = (cards: ViewNode[]): number =>
    cards.reduce((sum, { height }) => sum + height, 0) +
    STACK_GAP * Math.max(0, cards.length - 1);

/**
 * Where the top-left corner of each card goes, as dagre places the tree top-down with its manager over their reports.
 * The people who lead nobody under one manager are not tree leaves but one column: dagre places it as a single block
 * and the cards are then set one under the other, indented, so a team of 17 is tall instead of 4,000 px wide.
 */
export function layoutGraph(
    nodes: ViewNode[],
    edges: ViewEdge[],
): Map<string, Corner> {
    const graph = new Graph();
    const columns = Map.groupBy(
        nodes.filter(({ stack }) => stack !== null),
        ({ stack }) => stack,
    );

    graph.setGraph({ rankdir: 'TB', nodesep: SIBLING_GAP, ranksep: RANK_GAP });
    graph.setDefaultEdgeLabel(() => ({}));
    nodes
        .filter(({ stack }) => stack === null)
        .forEach(({ id, width, height }) =>
            graph.setNode(id, { width, height }),
        );
    columns.forEach((cards, manager) => {
        const width = Math.max(...cards.map((card) => card.width));

        graph.setNode(`column:${manager}`, {
            width: width + STACK_INDENT + 2 * STACK_MARGIN,
            height: columnHeight(cards),
        });
        graph.setEdge(String(manager), `column:${manager}`);
    });
    edges
        .filter(({ stacked }) => !stacked)
        .forEach(({ source, target }) => graph.setEdge(source, target));
    layout(graph);

    const places = new Map<string, Corner>();
    // dagre centers every node in its row, and a row is as tall as its tallest member (a column of 19 people is 1,500 px):
    // a card beside it would hang halfway down. Every card starts at the top of its row instead.
    const rowTops = new Map<number, number>();

    for (const id of graph.nodes()) {
        const placed = graph.node(id);
        const top = placed.y - placed.height / 2;

        rowTops.set(placed.y, Math.min(rowTops.get(placed.y) ?? top, top));
    }

    for (const { id, width, stack } of nodes) {
        if (stack === null) {
            const placed = graph.node(id);

            places.set(id, {
                x: placed.x - width / 2,
                y: rowTops.get(placed.y) ?? placed.y,
            });
        }
    }

    // dagre centers each node in its row, and a row is as tall as its tallest column: a short column would start halfway
    // down a long one beside it. Each column starts right under its manager instead; dagre still reserved its width.
    columns.forEach((cards, manager) => {
        const head = places.get(String(manager)) ?? { x: 0, y: 0 };
        const headHeight =
            nodes.find(({ id }) => id === String(manager))?.height ?? 0;
        let y = head.y + headHeight + RANK_GAP;

        for (const card of cards) {
            places.set(card.id, { x: head.x + STACK_INDENT, y });
            y += card.height + STACK_GAP;
        }
    });

    return new Map(
        nodes.map(({ id }) => [id, places.get(id) ?? { x: 0, y: 0 }]),
    );
}
