import { Rocket } from 'lucide-react';
import { describe, expect, it } from 'vitest';
import { ROADMAP_LANES } from '@/components/landing/roadmap-content';
import { areaCounts, filterLanes } from '@/lib/roadmap';
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
