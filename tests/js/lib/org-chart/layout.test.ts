import { describe, expect, it } from 'vite-plus/test';

import { layoutGraph, RANK_GAP } from '@/lib/org-chart/layout';
import type { ViewEdge, ViewNode } from '@/lib/org-chart/chart-view';

const card = (id: string, height = 72): ViewNode => ({
    id,
    width: 216,
    height,
    data: { kind: 'company', name: id, people: 0 },
});

const edge = (source: string, target: string): ViewEdge => ({
    id: `${source}>${target}`,
    source,
    target,
    highlighted: false,
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
            216 + 24,
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
