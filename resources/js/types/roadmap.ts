import type { LucideIcon } from 'lucide-react';

/** What a tool is about, so the roadmap can be filtered by what a reader cares for. */
export type RoadmapArea = 'evaluation' | 'compliance' | 'talent' | 'operation';

export type RoadmapStage = 'now' | 'soon' | 'later' | 'exploring';

/** An example drawn beside a tool, to show what it will look like. */
export type RoadmapSketch = 'goals' | 'feedback';

export type RoadmapItem = {
    title: string;
    text: string;
    area: RoadmapArea;
    icon: LucideIcon;
    sketch?: RoadmapSketch;
};

export type RoadmapLane = {
    id: RoadmapStage;
    title: string;
    summary: string;
    status: string;
    items: readonly RoadmapItem[];
};
