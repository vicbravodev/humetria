import { ROADMAP_LANES } from '@/components/landing/roadmap-content';
import RoadmapStageDot from '@/components/landing/roadmap-stage-dot';

/** How to read the page: what each stage means, with the same dot the stage carries below. */
export default function RoadmapLegend() {
    return (
        <dl className="grid content-start gap-3 rounded-2xl border bg-card p-5 lg:p-6">
            {ROADMAP_LANES.map((lane) => (
                <div
                    key={lane.id}
                    className="grid grid-cols-[auto_1fr] items-baseline gap-x-3"
                >
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
