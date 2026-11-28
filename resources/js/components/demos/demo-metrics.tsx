import { Skeleton } from '@/components/ui/skeleton';
import { demoMetricTiles } from '@/lib/demo-requests';
import type { DemoMetrics as Metrics } from '@/types';

/**
 * The three figures of the funnel (tablero Demos; the design system allows three a screen): what waits, how soon the team
 * answers and how many became a company, with where most came from. They arrive after the page shows; until then each tile keeps its size, so nothing below moves.
 */
export default function DemoMetrics({
    metrics,
}: {
    metrics: Metrics | undefined;
}) {
    return (
        <div className="grid grid-cols-[repeat(auto-fit,minmax(min(100%,200px),1fr))] gap-4">
            {demoMetricTiles(metrics).map((tile) => (
                <section
                    key={tile.title}
                    className="grid content-start gap-1 card p-6"
                >
                    <h2 className="text-caption font-medium tracking-[0.02em] text-muted-foreground uppercase">
                        {tile.title}
                    </h2>
                    {tile.figure === null ? (
                        <Skeleton className="h-10 w-16" />
                    ) : (
                        <p className="truncate text-[32px] leading-10 font-semibold tracking-[-0.01em] tabular-nums">
                            {tile.figure}
                        </p>
                    )}
                    <p className="min-h-5 text-sm text-muted-foreground">
                        {tile.caption}
                    </p>
                </section>
            ))}
        </div>
    );
}
