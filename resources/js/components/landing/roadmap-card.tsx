import IconTile from '@/components/humetria/icon-tile';
import { AREA_LABELS } from '@/components/landing/roadmap-content';
import RoadmapFeedbackSketch from '@/components/landing/roadmap-feedback-sketch';
import RoadmapGoalsSketch from '@/components/landing/roadmap-goals-sketch';
import { cn } from '@/lib/utils';
import type { RoadmapItem } from '@/types/roadmap';

/** The classes are written out so Tailwind finds them: how many of the six columns a card takes, by screen. */
const SM_SPANS: Record<number, string> = {
    3: 'sm:col-span-3',
    6: 'sm:col-span-6',
};
const LG_SPANS: Record<number, string> = {
    2: 'lg:col-span-2',
    3: 'lg:col-span-3',
    6: 'lg:col-span-6',
};

/**
 * One tool: its icon and name, what it does and, for the ones coming first, a made-up view of how it will look; its area sits
 * at the foot so the tags of a row line up. It fades in with CSS each time it mounts (the filter shows it): motion would print a
 * style="" the CSP blocks. `sm` and `lg` are the columns it takes, so every row is full.
 */
export default function RoadmapCard({
    item,
    sm,
    lg,
}: {
    item: RoadmapItem;
    sm: number;
    lg: number;
}) {
    return (
        <li
            className={cn(
                'enter-details list-none',
                SM_SPANS[sm],
                LG_SPANS[lg],
            )}
        >
            <div className="flex h-full flex-col gap-3 card p-4 transition-[border-color,box-shadow,transform] duration-200 hover:border-primary/40 hover:shadow-raised motion-safe:hover:-translate-y-0.5">
                <div className="flex items-center gap-3">
                    <IconTile icon={item.icon} />
                    <h3 className="leading-snug font-semibold text-balance">
                        {item.title}
                    </h3>
                </div>
                {item.sketch === 'goals' && <RoadmapGoalsSketch />}
                {item.sketch === 'feedback' && <RoadmapFeedbackSketch />}
                <p className="leading-6 text-pretty text-muted-foreground">
                    {item.text}
                </p>
                <span className="mt-auto w-fit rounded-full border px-2.5 py-0.5 text-meta text-muted-foreground">
                    {AREA_LABELS[item.area]}
                </span>
            </div>
        </li>
    );
}
