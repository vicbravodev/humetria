import { AnimatePresence } from 'motion/react';
import RoadmapCard from '@/components/landing/roadmap-card';
import RoadmapStageLabel from '@/components/landing/roadmap-stage-label';
import type { RoadmapLane } from '@/types/roadmap';

/** One stage of the roadmap: its side label and a card for each tool in it; the cards glide when the filter changes. */
export default function RoadmapLaneSection({ lane }: { lane: RoadmapLane }) {
    return (
        <section
            id={lane.id}
            aria-labelledby={`${lane.id}-title`}
            className="grid scroll-mt-36 gap-6 border-t pt-10 first:border-t-0 first:pt-0 md:grid-cols-[220px_minmax(0,1fr)] md:gap-10"
        >
            <RoadmapStageLabel lane={lane} />
            <ul className="grid content-start gap-4 sm:grid-cols-2">
                <AnimatePresence mode="popLayout" initial={false}>
                    {lane.items.map((item) => (
                        <RoadmapCard key={item.title} item={item} />
                    ))}
                </AnimatePresence>
            </ul>
        </section>
    );
}
