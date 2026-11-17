import { m } from 'motion/react';
import { EASE_OUT } from '@/components/landing/landing-motion';
import { AREA_LABELS } from '@/components/landing/roadmap-content';
import RoadmapFeedbackSketch from '@/components/landing/roadmap-feedback-sketch';
import RoadmapGoalsSketch from '@/components/landing/roadmap-goals-sketch';
import IconTile from '@/components/humetria/icon-tile';
import type { RoadmapItem } from '@/types/roadmap';

/** One tool: its icon, its area, what it does and, for the ones coming first, a made-up view of how it will look. */
export default function RoadmapCard({ item }: { item: RoadmapItem }) {
    return (
        <m.li
            layout
            initial={{ opacity: 0, scale: 0.97 }}
            animate={{ opacity: 1, scale: 1 }}
            exit={{ opacity: 0, scale: 0.97 }}
            transition={{ duration: 0.22, ease: EASE_OUT }}
            className="list-none"
        >
            <div className="grid h-full content-start gap-4 rounded-xl border bg-card p-5 transition-[border-color,box-shadow,transform] duration-200 hover:border-primary/40 hover:shadow-raised motion-safe:hover:-translate-y-0.5">
                <div className="flex items-start justify-between gap-3">
                    <IconTile icon={item.icon} />
                    <span className="rounded-full border px-2.5 py-0.5 text-meta text-muted-foreground">
                        {AREA_LABELS[item.area]}
                    </span>
                </div>
                {item.sketch === 'goals' && <RoadmapGoalsSketch />}
                {item.sketch === 'feedback' && <RoadmapFeedbackSketch />}
                <div className="grid gap-1.5">
                    <h3 className="font-semibold">{item.title}</h3>
                    <p className="leading-6 text-pretty text-muted-foreground">
                        {item.text}
                    </p>
                </div>
            </div>
        </m.li>
    );
}
