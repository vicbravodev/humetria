import { describe, expect, it } from 'vite-plus/test';

import {
    layoutGraph,
    RANK_GAP,
    SIBLING_GAP,
    STACK_GAP,
    STACK_INDENT,
} from '@/lib/org-chart/layout';
import type { ViewEdge, ViewNode } from '@/lib/org-chart/chart-view';

const card = (
    id: string,
    height = 72,
    stack: string | null = null,
): ViewNode => ({
    id,
    width: 216,
    height,
    stack,
    data: { kind: 'company', name: id, people: 0 },
});

const edge = (source: string, target: string): ViewEdge => ({
    id: `${source}>${target}`,
    source,
    target,
    highlighted: false,
    stacked: false,
});

describe('layoutGraph', () => {
    const nodes = [card('top'), card('a'), card('b'), card('c')];
    const edges = [edge('top', 'a'), edge('top', 'b'), edge('a', 'c')];
    const at = layoutGraph(nodes, edges);
    const where = (id: string) => {
        const place = at.get(id);

        if (place === undefined) {
            throw new Error(`no place for ${id}`);
        }

        return place;
    };

    it('puts every person at the top-left corner of their card, with a place for each', () => {
        expect([...at.keys()].toSorted((x, y) => x.localeCompare(y))).toEqual([
            'a',
            'b',
            'c',
            'top',
        ]);
    });

    it('puts the reports below their manager, one level per row, with room for the pill and the elbow', () => {
        expect(where('a').y).toBe(where('top').y + 72 + RANK_GAP);
        expect(where('c').y).toBe(where('a').y + 72 + RANK_GAP);
        expect(where('b').y).toBe(where('a').y);
    });

    it('centers the manager over their reports and keeps siblings apart', () => {
        const middle = (where('a').x + where('b').x) / 2;

        expect(Math.abs(where('top').x - middle)).toBeLessThan(1);
        expect(Math.abs(where('a').x - where('b').x)).toBeGreaterThanOrEqual(
            216 + SIBLING_GAP,
        );
    });

    it('lays out nothing for an empty chart', () => {
        expect(layoutGraph([], []).size).toBe(0);
    });

    it('gives a short card a row of its own height', () => {
        const small = layoutGraph(
            [card('top'), card('more', 44)],
            [edge('top', 'more')],
        );

        expect(small.get('more')?.y).toBe(72 + RANK_GAP);
    });
});

describe('layoutGraph, stacks', () => {
    const nodes = [
        card('top'),
        card('a'),
        card('a1', 72, 'a'),
        card('a2', 72, 'a'),
        card('a3', 44, 'a'),
        card('b'),
        card('b1', 72, 'b'),
        card('b2', 72, 'b'),
    ];
    const edges = [
        edge('top', 'a'),
        edge('top', 'b'),
        { ...edge('a', 'a1'), stacked: true },
        { ...edge('a', 'a2'), stacked: true },
        { ...edge('a', 'a3'), stacked: true },
        { ...edge('b', 'b1'), stacked: true },
        { ...edge('b', 'b2'), stacked: true },
    ];
    const at = layoutGraph(nodes, edges);
    const where = (id: string) => {
        const place = at.get(id);

        if (place === undefined) {
            throw new Error(`no place for ${id}`);
        }

        return place;
    };

    it('hangs the stacked people in a column, indented under their manager, one under the other', () => {
        expect(where('a1').x).toBe(where('a').x + STACK_INDENT);
        expect(where('a2').x).toBe(where('a1').x);
        expect(where('a2').y).toBe(where('a1').y + 72 + STACK_GAP);
        expect(where('a3').y).toBe(where('a2').y + 72 + STACK_GAP);
        expect(where('a1').y).toBe(where('a').y + 72 + RANK_GAP);
    });

    it('starts a short column right under its manager, not halfway down a taller one beside it', () => {
        expect(where('b1').y).toBe(where('b').y + 72 + RANK_GAP);
        expect(where('b1').y).toBe(where('a1').y);
    });

    it('reserves the whole column of each manager, so two stacks side by side do not overlap', () => {
        const [first, second] = [where('a1'), where('b1')].toSorted(
            (left, right) => left.x - right.x,
        );

        expect((second?.x ?? 0) - (first?.x ?? 0)).toBeGreaterThanOrEqual(216);
        expect(where('a').y).toBe(where('b').y);
    });
});
