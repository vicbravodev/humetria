import { Skeleton } from '@/components/ui/skeleton';
import { demoMetricTiles } from '@/lib/demo-requests';
import type { DemoMetrics as Metrics } from '@/types';

/**
 * The four figures of the funnel: what waits, how soon the team answers, how many became a company and where most
 * came from. They arrive after the page shows; until then each tile keeps its size, so nothing below it moves.
 */
export default function DemoMetrics({
    metrics,
}: {
    metrics: Metrics | undefined;
}) {
    return (
        <div className="grid grid-cols-2 gap-3 md:gap-4 lg:grid-cols-4">
            {demoMetricTiles(metrics).map((tile) => (
                <section
                    key={tile.title}
                    className="grid content-start gap-1 card p-4 md:p-5"
                >
                    <h2 className="text-caption text-muted-foreground">
                        {tile.title}
                    </h2>
                    {tile.figure === null ? (
                        <Skeleton className="h-8 w-16 md:h-9" />
                    ) : (
                        <p className="truncate text-2xl font-semibold tracking-[-0.01em] tabular-nums md:text-3xl">
                            {tile.figure}
                        </p>
                    )}
                    <p className="min-h-10 text-sm text-muted-foreground lg:min-h-5">
                        {tile.caption}
                    </p>
                </section>
            ))}
        </div>
    );
}
