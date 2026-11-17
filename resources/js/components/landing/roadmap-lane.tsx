import { Clock, Compass, Hourglass, Rocket } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { ICON_BOX } from '@/components/landing/landing-layout';
import type { RoadmapLane } from '@/components/landing/roadmap-content';

const ICONS: Record<RoadmapLane['id'], LucideIcon> = {
    now: Rocket,
    soon: Clock,
    later: Hourglass,
    exploring: Compass,
};

/** One lane of the roadmap: its name, what it means, and a card for each tool in it. */
export default function RoadmapLaneSection({ lane }: { lane: RoadmapLane }) {
    const Icon = ICONS[lane.id];

    return (
        <section
            id={lane.id}
            aria-labelledby={`${lane.id}-title`}
            className="grid scroll-mt-24 gap-6 border-t pt-8 first:border-t-0 first:pt-0"
        >
            <div className="flex items-center gap-3">
                <span aria-hidden className={`${ICON_BOX} size-10`}>
                    <Icon className="size-5" />
                </span>
                <div className="grid gap-0.5">
                    <h2
                        id={`${lane.id}-title`}
                        className="text-2xl font-semibold tracking-[-0.02em]"
                    >
                        {lane.title}
                    </h2>
                    <p className="text-muted-foreground">{lane.summary}</p>
                </div>
            </div>
            <ul className="grid gap-4 sm:grid-cols-2">
                {lane.items.map((item) => (
                    <li
                        key={item.title}
                        className="grid content-start gap-1.5 rounded-xl border bg-card p-5"
                    >
                        <h3 className="font-semibold">{item.title}</h3>
                        <p className="leading-6 text-pretty text-muted-foreground">
                            {item.text}
                        </p>
                    </li>
                ))}
            </ul>
        </section>
    );
}
