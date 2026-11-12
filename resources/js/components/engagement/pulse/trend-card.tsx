import TrendArrow from '@/components/engagement/pulse/trend-arrow';
import TrendColumns from '@/components/engagement/pulse/trend-columns';
import { changeSincePrevious } from '@/lib/engagement/trend';
import type { TrendSeries } from '@/types';

/** One figure across the waves: its name, what it measures, the columns and the arrow of the latest wave. */
export default function TrendCard({ series }: { series: TrendSeries }) {
    const latest = series.points.at(-1);

    return (
        <article className="flex flex-col gap-4 card p-5 sm:p-6">
            <header className="flex items-start justify-between gap-3">
                <div className="flex flex-col gap-1">
                    <h2 className="text-lg font-semibold">{series.title}</h2>
                    {series.hint !== null && (
                        <p className="text-sm text-muted-foreground">
                            {series.hint}
                        </p>
                    )}
                </div>
                <TrendArrow change={changeSincePrevious(series.points)} />
            </header>
            {latest === undefined ? (
                <p className="text-sm text-muted-foreground">
                    Ninguna ola permite mostrarlo todavía: se necesita que más
                    personas hayan contestado esta pregunta.
                </p>
            ) : (
                <TrendColumns series={series} />
            )}
        </article>
    );
}
