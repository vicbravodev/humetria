import { Clock, Compass, Hourglass, Rocket } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { RoadmapLane, RoadmapStage } from '@/types/roadmap';

const ICONS: Record<RoadmapStage, LucideIcon> = {
    now: Rocket,
    soon: Clock,
    later: Hourglass,
    exploring: Compass,
};

/** How the dot of the status looks: alive for what is being built, hollow and fainter the further away a stage is. */
const DOTS: Record<RoadmapStage, string> = {
    now: 'bg-primary',
    soon: 'border-2 border-primary',
    later: 'bg-muted-foreground/60',
    exploring: 'border-2 border-dashed border-muted-foreground',
};

/** The side of a stage: what it is called, how close it is and how many tools it holds. */
export default function RoadmapStageLabel({ lane }: { lane: RoadmapLane }) {
    const Icon = ICONS[lane.id];

    return (
        <div className="grid content-start gap-3 md:sticky md:top-36 md:self-start">
            <div className="flex items-center gap-3">
                <span
                    aria-hidden
                    className={cn(
                        'flex size-11 items-center justify-center rounded-xl',
                        lane.id === 'now'
                            ? 'bg-primary text-primary-foreground'
                            : 'bg-accent text-primary',
                    )}
                >
                    <Icon className="size-5" />
                </span>
                <h2
                    id={`${lane.id}-title`}
                    className="text-2xl font-semibold tracking-[-0.02em]"
                >
                    {lane.title}
                </h2>
            </div>
            <p className="flex items-center gap-2 text-meta font-medium">
                <span aria-hidden className="relative flex size-2.5">
                    {lane.id === 'now' && (
                        <span className="absolute inline-flex size-full rounded-full bg-primary opacity-60 motion-safe:animate-ping" />
                    )}
                    <span
                        className={cn(
                            'relative inline-flex size-2.5 rounded-full',
                            DOTS[lane.id],
                        )}
                    />
                </span>
                {lane.status}
            </p>
            <p className="max-w-[28ch] text-pretty text-muted-foreground">
                {lane.summary}
            </p>
        </div>
    );
}
