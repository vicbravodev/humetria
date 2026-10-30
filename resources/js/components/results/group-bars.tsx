import type { ReactNode } from 'react';
import LevelLegend from '@/components/humetria/level-legend';
import DistributionTable from '@/components/results/distribution-table';
import GroupBarRow from '@/components/results/group-bar-row';
import { rankBySeverity } from '@/lib/results';
import type { DistributionRow, RiskLevelInfo } from '@/types';

/**
 * Groups (categories, domains, areas) from the worst off, each as a bar, so the eye lands first where the norm asks
 * to act. The counts behind the bars stay a click away.
 */
export default function GroupBars({
    heading,
    description,
    noun,
    rows,
    levels,
    caption,
}: {
    heading: ReactNode;
    description?: ReactNode;
    /** What one row is, for the column head: "Dominio", "Categoría", "Área". */
    noun: string;
    rows: DistributionRow[];
    levels: RiskLevelInfo[];
    caption: string;
}) {
    return (
        <section className="grid break-inside-avoid-page gap-4">
            <div className="grid gap-1">
                {heading}
                {description !== undefined && (
                    <p className="text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            <div className="grid gap-3 rounded-xl border bg-card p-4 sm:p-5">
                <div className="hidden text-[13px] leading-[18px] font-medium tracking-[0.02em] text-muted-foreground uppercase sm:grid sm:grid-cols-[minmax(0,15rem)_1fr_7.5rem] sm:gap-5">
                    <span>{noun}</span>
                    <span>Cuestionarios por nivel</span>
                    <span className="text-right">Alto o muy alto</span>
                </div>
                <ol className="divide-y">
                    {rankBySeverity(rows).map((row) => (
                        <GroupBarRow key={row.key} row={row} levels={levels} />
                    ))}
                </ol>
                <LevelLegend levels={levels} className="border-t pt-3" />
            </div>
            <details className="group">
                <summary className="w-fit cursor-pointer list-none rounded-md text-sm font-medium text-primary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring [&::-webkit-details-marker]:hidden">
                    <span className="group-open:hidden">Ver los conteos</span>
                    <span className="hidden group-open:inline">
                        Ocultar los conteos
                    </span>
                </summary>
                <div className="mt-3">
                    <DistributionTable
                        caption={caption}
                        heading={noun}
                        rows={rows}
                        levels={levels}
                    />
                </div>
            </details>
        </section>
    );
}
