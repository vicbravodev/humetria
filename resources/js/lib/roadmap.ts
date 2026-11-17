import type { RoadmapArea, RoadmapLane } from '@/types/roadmap';

export type AreaFilter = RoadmapArea | 'all';

/** The lanes with only the items of the area; a lane left empty goes away, so no heading is left with nothing under it. */
export function filterLanes(
    lanes: readonly RoadmapLane[],
    area: AreaFilter,
): readonly RoadmapLane[] {
    if (area === 'all') {
        return lanes;
    }

    return lanes
        .map((lane) => ({
            ...lane,
            items: lane.items.filter((item) => item.area === area),
        }))
        .filter((lane) => lane.items.length > 0);
}

/** How many tools there are in all and in each area, for the number beside each filter. */
export function areaCounts(
    lanes: readonly RoadmapLane[],
): Record<AreaFilter, number> {
    const counts: Record<AreaFilter, number> = {
        all: 0,
        evaluation: 0,
        compliance: 0,
        talent: 0,
        operation: 0,
    };

    for (const lane of lanes) {
        for (const item of lane.items) {
            counts.all += 1;
            counts[item.area] += 1;
        }
    }

    return counts;
}
