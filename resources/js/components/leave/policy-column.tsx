import type { ReactNode } from 'react';
import ColumnChart from '@/components/humetria/column-chart';
import { Badge } from '@/components/ui/badge';
import { yearColumns } from '@/lib/leave/compare';
import { countOf } from '@/lib/plural';

export type PolicyColumnData = {
    name: string;
    firstYearDays: number;
    premiumPercent: number;
    /** The line of the fixed days, or null for none. */
    fixed: string | null;
    /** The days by year of service, 1 to 6. */
    byYear: number[];
    /** Who it applies to, in words: groups and how many people. */
    applies: string;
    daysDelta: string | null;
    premiumDelta: string | null;
};

/**
 * One column of the comparison: the figures of a policy (or of the law) on the same rows as the others, the improvement over
 * the law next to each, and under them its days by seniority as columns from zero with the law as a mark.
 */
export default function PolicyColumn({
    column,
    lawByYear,
    top,
    badge,
    actions,
}: {
    column: PolicyColumnData;
    lawByYear: number[];
    top: number;
    badge?: string;
    actions?: ReactNode;
}) {
    const rows: [string, string, string | null][] = [
        [
            'Días el primer año',
            countOf(column.firstYearDays, 'día', 'días'),
            column.daysDelta,
        ],
        ['Prima vacacional', `${column.premiumPercent} %`, column.premiumDelta],
        ['Días fijos', column.fixed ?? '—', null],
        ['A quién aplica', column.applies, null],
    ];

    return (
        <article
            aria-label={column.name}
            className="flex min-w-0 flex-col gap-5 card p-5"
        >
            <header className="flex items-start gap-2">
                <h2 className="min-w-0 flex-1 text-lg leading-[26px] font-semibold">
                    {column.name}
                </h2>
                {badge !== undefined && (
                    <Badge variant="secondary">{badge}</Badge>
                )}
                {actions}
            </header>
            <dl className="flex flex-col gap-3">
                {rows.map(([label, value, delta]) => (
                    <div key={label} className="flex flex-col gap-0.5">
                        <dt className="text-caption text-muted-foreground">
                            {label}
                        </dt>
                        <dd className="flex flex-wrap items-baseline gap-x-2 font-medium">
                            {value}
                            {delta !== null && (
                                <span className="text-meta font-medium text-primary">
                                    {delta}
                                </span>
                            )}
                        </dd>
                    </div>
                ))}
            </dl>
            <ColumnChart
                label={`Días por antigüedad de ${column.name}`}
                columns={yearColumns(column.byYear, lawByYear, top)}
            />
        </article>
    );
}
