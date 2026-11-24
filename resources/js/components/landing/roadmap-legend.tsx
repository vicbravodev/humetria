import { ROADMAP_LANES } from '@/components/landing/roadmap-content';
import RoadmapStageDot from '@/components/landing/roadmap-stage-dot';

/** How to read the page: what each stage means, with the same dot the stage carries below. One shared grid, so the meanings line up in a column instead of stepping with the length of each name. */
export default function RoadmapLegend() {
    return (
        <dl className="grid grid-cols-[max-content_1fr] content-start items-baseline gap-x-4 gap-y-4 rounded-2xl border bg-card p-5 lg:p-6">
            {ROADMAP_LANES.map((lane) => (
                <div key={lane.id} className="contents">
                    <dt className="flex items-center gap-2 font-semibold">
                        <RoadmapStageDot stage={lane.id} />
                        {lane.title}
                    </dt>
                    <dd className="text-meta text-muted-foreground">
                        {lane.summary}
                    </dd>
                </div>
            ))}
        </dl>
    );
}
