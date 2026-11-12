import { TrendingUp } from 'lucide-react';
import TrendColumns from '@/components/engagement/pulse/trend-columns';
import TrendSince from '@/components/engagement/pulse/trend-since';
import {
    changeSinceFirst,
    changeSincePrevious,
    columnsOf,
    versusPreviousText,
} from '@/lib/engagement/trend';
import { cn } from '@/lib/utils';
import type { TrendSeries } from '@/types';

/**
 * One index across the waves: the latest figure with how far it moved since the first measurement, what it measures and
 * its columns from zero. The protagonist (the engagement index) also reads against the wave before and draws the first
 * measurement as its dashed baseline.
 */
export default function TrendCard({
    series,
    prominent = false,
}: {
    series: TrendSeries;
    prominent?: boolean;
}) {
    const latest = series.points.at(-1);

    return (
        <article
            className={cn(
                'flex min-w-0 flex-col gap-4 card p-5 sm:p-6',
                prominent && 'border-primary',
            )}
        >
            <h2 className="flex items-center gap-2 text-sm font-normal text-muted-foreground">
                {prominent && <TrendingUp aria-hidden className="size-4" />}
                {series.title}
            </h2>
            {latest === undefined ? (
                <p className="text-sm text-muted-foreground">
                    Ninguna ola permite mostrarlo todavía: se necesita que más
                    personas hayan contestado esta pregunta.
                </p>
            ) : (
                <>
                    <p className="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                        <span
                            className={cn(
                                'font-semibold tracking-[-0.02em] tabular-nums',
                                prominent ? 'text-[32px]' : 'text-2xl',
                            )}
                        >
                            {columnsOf([latest], series.kind)[0]?.valueText}
                        </span>
                        <TrendSince change={changeSinceFirst(series.points)} />
                    </p>
                    <p className="text-sm text-muted-foreground">
                        {prominent &&
                            `${versusPreviousText(changeSincePrevious(series.points))} `}
                        {series.hint}
                    </p>
                    <TrendColumns series={series} withBaseline={prominent} />
                </>
            )}
        </article>
    );
}
