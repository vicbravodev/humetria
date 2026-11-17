import { Clock, Compass, Hourglass, Rocket } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import RoadmapStageDot from '@/components/landing/roadmap-stage-dot';
import { cn } from '@/lib/utils';
import type { RoadmapLane, RoadmapStage } from '@/types/roadmap';

const ICONS: Record<RoadmapStage, LucideIcon> = {
    now: Rocket,
    soon: Clock,
    later: Hourglass,
    exploring: Compass,
};

/** The head of a stage, in one line: its icon and name, how close it is and how many tools it holds. */
export default function RoadmapStageLabel({ lane }: { lane: RoadmapLane }) {
    const Icon = ICONS[lane.id];

    return (
        <div className="flex flex-wrap items-center gap-x-4 gap-y-1.5">
            <span
                aria-hidden
                className={cn(
                    'flex size-10 items-center justify-center rounded-xl',
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
            <p className="flex items-center gap-2 text-meta font-medium">
                <RoadmapStageDot stage={lane.id} />
                {lane.status}
            </p>
            <p className="text-meta text-muted-foreground sm:ml-auto">
                {lane.items.length === 1
                    ? '1 herramienta'
                    : `${lane.items.length} herramientas`}
            </p>
        </div>
    );
}
