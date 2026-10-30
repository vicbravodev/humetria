import type { ReactNode } from 'react';
import LevelLegend from '@/components/humetria/level-legend';
import DistributionTable from '@/components/results/distribution-table';
import GroupBarRow from '@/components/results/group-bar-row';
import { rankBySeverity } from '@/lib/results';
import type { DistributionRow, RiskLevelInfo } from '@/types';

/**
 * Groups (categories, domains, areas) from the worst off, each as a bar, so the eye lands first where the norm asks
 * to act (pantallas: Eval-Activa, "Participación por área"). The counts behind the bars stay a click away, or in
 * plain sight in the report.
 */
export default function GroupBars({
    heading,
    description,
    noun,
    rows,
    levels,
    caption,
    tableOpen = false,
}: {
    heading: ReactNode;
    description?: ReactNode;
    /** What one row is, for the column head: "Dominio", "Categoría", "Área". */
    noun: string;
    rows: DistributionRow[];
    levels: RiskLevelInfo[];
    caption: string;
    /** Show the counts table at once (the report), instead of behind "Ver los conteos". */
    tableOpen?: boolean;
}) {
    const table = (
        <DistributionTable
            caption={caption}
            heading={noun}
            rows={rows}
            levels={levels}
        />
    );

    return (
        <section className="grid break-inside-avoid-page gap-6 rounded-xl border bg-card p-5 sm:p-6 print:border-0 print:p-0">
            <div className="grid gap-x-10 gap-y-6 lg:grid-cols-3">
                <div className="flex flex-col gap-2">
                    {heading}
                    {description !== undefined && (
                        <p className="text-sm text-muted-foreground">
                            {description}
                        </p>
                    )}
                </div>
                <div className="flex flex-col gap-5 lg:col-span-2">
                    <ol className="flex flex-col gap-4">
                        {rankBySeverity(rows).map((row) => (
                            <GroupBarRow
                                key={row.key}
                                row={row}
                                levels={levels}
                            />
                        ))}
                    </ol>
                    <LevelLegend levels={levels} className="border-t pt-4" />
                </div>
            </div>
            {tableOpen ? (
                table
            ) : (
                <details className="group">
                    <summary className="w-fit cursor-pointer list-none rounded-md text-sm font-medium text-primary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring [&::-webkit-details-marker]:hidden">
                        <span className="group-open:hidden">
                            Ver los conteos
                        </span>
                        <span className="hidden group-open:inline">
                            Ocultar los conteos
                        </span>
                    </summary>
                    <div className="mt-3">{table}</div>
                </details>
            )}
        </section>
    );
}
