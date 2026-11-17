import { cn } from '@/lib/utils';
import type { RoadmapStage } from '@/types/roadmap';

/** How the dot looks: alive for what is being built, hollow and fainter the further away a stage is. */
const DOTS: Record<RoadmapStage, string> = {
    now: 'bg-primary',
    soon: 'border-2 border-primary',
    later: 'bg-muted-foreground/60',
    exploring: 'border-2 border-dashed border-muted-foreground',
};

/** The dot that says how close a stage is. */
export default function RoadmapStageDot({ stage }: { stage: RoadmapStage }) {
    return (
        <span aria-hidden className="relative flex size-2.5 shrink-0">
            {stage === 'now' && (
                <span className="absolute inline-flex size-full rounded-full bg-primary opacity-60 motion-safe:animate-ping" />
            )}
            <span
                className={cn(
                    'relative inline-flex size-2.5 rounded-full',
                    DOTS[stage],
                )}
            />
        </span>
    );
}
