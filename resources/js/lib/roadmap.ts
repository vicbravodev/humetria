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

/**
 * How many cards go in each row of a grid of `perRow` columns, so no row is left half empty: a lone card in a row of three
 * takes a place in the row before it (two and two instead of three and one). A lone card in a row of two keeps its row.
 */
export function rowSizes(count: number, perRow: 2 | 3): number[] {
    const rows = Array.from(
        { length: Math.floor(count / perRow) },
        () => perRow,
    );
    const rest = count % perRow;

    if (rest === 0) {
        return rows;
    }

    if (perRow === 3 && rest === 1 && rows.length > 0) {
        rows.pop();

        return [...rows, 2, 2];
    }

    return [...rows, rest];
}

/** The columns, out of six, each card takes so its row is filled: three cards take two each, two take three, one takes six. */
export function columnSpans(count: number, perRow: 2 | 3): number[] {
    return rowSizes(count, perRow).flatMap((size) =>
        Array.from({ length: size }, () => 6 / size),
    );
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
