import { Rocket } from 'lucide-react';
import { describe, expect, it } from 'vitest';
import { ROADMAP_LANES } from '@/components/landing/roadmap-content';
import { areaCounts, columnSpans, filterLanes, rowSizes } from '@/lib/roadmap';
import type { RoadmapLane } from '@/types/roadmap';

const lanes: readonly RoadmapLane[] = [
    {
        id: 'now',
        title: 'Ahora',
        summary: 'a',
        status: 'En construcción',
        items: [
            { title: 'A', text: 'a', area: 'evaluation', icon: Rocket },
            { title: 'B', text: 'b', area: 'compliance', icon: Rocket },
        ],
    },
    {
        id: 'soon',
        title: 'Pronto',
        summary: 'b',
        status: 'Con alcance definido',
        items: [{ title: 'C', text: 'c', area: 'compliance', icon: Rocket }],
    },
];

describe('filterLanes', () => {
    it('keeps every lane and item for all', () => {
        expect(filterLanes(lanes, 'all')).toEqual(lanes);
    });

    it('keeps only the items of the area', () => {
        const titles = filterLanes(lanes, 'compliance').map((lane) =>
            lane.items.map((item) => item.title),
        );

        expect(titles).toEqual([['B'], ['C']]);
    });

    it('drops a lane left without items', () => {
        const kept = filterLanes(lanes, 'evaluation');

        expect(kept.map((lane) => lane.id)).toEqual(['now']);
    });

    it('returns nothing for an area with no items', () => {
        expect(filterLanes(lanes, 'talent')).toEqual([]);
    });
});

describe('areaCounts', () => {
    it('counts all the items and those of each area', () => {
        expect(areaCounts(lanes)).toEqual({
            all: 3,
            evaluation: 1,
            compliance: 2,
            talent: 0,
            operation: 0,
        });
    });
});

describe('rowSizes', () => {
    it.each([
        [1, 3, [1]],
        [2, 3, [2]],
        [3, 3, [3]],
        [4, 3, [2, 2]],
        [5, 3, [3, 2]],
        [7, 3, [3, 2, 2]],
        [8, 3, [3, 3, 2]],
        [4, 2, [2, 2]],
        [5, 2, [2, 2, 1]],
    ] as const)(
        'splits %i cards into rows of up to %i',
        (count, perRow, rows) => {
            expect(rowSizes(count, perRow)).toEqual(rows);
        },
    );

    it('has no rows for no cards', () => {
        expect(rowSizes(0, 3)).toEqual([]);
    });
});

describe('columnSpans', () => {
    it('gives each card the share of its row, out of six columns', () => {
        expect(columnSpans(7, 3)).toEqual([2, 2, 2, 3, 3, 3, 3]);
        expect(columnSpans(5, 2)).toEqual([3, 3, 3, 3, 6]);
        expect(columnSpans(1, 3)).toEqual([6]);
    });
});

describe('the roadmap content', () => {
    it('opens with the evaluation tools in the first stage', () => {
        const [first] = ROADMAP_LANES;

        expect(first?.id).toBe('now');
        expect(first?.items.slice(0, 2).map((item) => item.area)).toEqual([
            'evaluation',
            'evaluation',
        ]);
    });

    it('never repeats a tool and gives every one an area', () => {
        const titles = ROADMAP_LANES.flatMap((lane) =>
            lane.items.map((item) => item.title),
        );

        expect(new Set(titles).size).toBe(titles.length);
    });
});
