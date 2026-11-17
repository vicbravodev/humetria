import RoadmapCard from '@/components/landing/roadmap-card';
import RoadmapStageLabel from '@/components/landing/roadmap-stage-label';
import { columnSpans } from '@/lib/roadmap';
import type { RoadmapLane } from '@/types/roadmap';

/** One stage of the roadmap: its head and a card for each tool, sized so every row is full. */
export default function RoadmapLaneSection({ lane }: { lane: RoadmapLane }) {
    const sketched = lane.items.filter((item) => item.sketch !== undefined);
    const plain = lane.items.filter((item) => item.sketch === undefined);
    const wide = [
        ...columnSpans(sketched.length, 3),
        ...columnSpans(plain.length, 3),
    ];
    const narrow = columnSpans(lane.items.length, 2);

    return (
        <section
            id={lane.id}
            aria-labelledby={`${lane.id}-title`}
            className="grid scroll-mt-36 gap-5 border-t pt-8 first:border-t-0 first:pt-0"
        >
            <RoadmapStageLabel lane={lane} />
            <ul className="grid grid-cols-1 gap-4 sm:grid-cols-6">
                {[...sketched, ...plain].map((item, index) => (
                    <RoadmapCard
                        key={item.title}
                        item={item}
                        sm={narrow[index] ?? 3}
                        lg={wide[index] ?? 2}
                    />
                ))}
            </ul>
        </section>
    );
}
