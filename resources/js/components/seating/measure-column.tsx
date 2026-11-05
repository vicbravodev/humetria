import MeasureCard from '@/components/seating/measure-card';
import type { MeasureColumn as Column } from '@/lib/seating/plan';
import { cn } from '@/lib/utils';

const DOT: Record<Column['status'], string> = {
    planned: 'border-[1.5px] border-muted-foreground',
    in_progress:
        'border-[1.5px] border-primary bg-[linear-gradient(90deg,var(--primary)_50%,transparent_50%)]',
    done: 'bg-primary',
};

/** One column of the plan (board LS-Plan): planned, in progress or done, with how many and each measure as a card. */
export default function MeasureColumn({
    workCenterId,
    column,
    canManage,
}: {
    workCenterId: string;
    column: Column;
    canManage: boolean;
}) {
    const id = `measures-${column.status}`;

    return (
        <section
            aria-labelledby={id}
            className="flex min-h-40 flex-col gap-3 rounded-2xl bg-muted p-3"
        >
            <div className="flex items-center gap-2 px-1 py-1">
                <span
                    aria-hidden
                    className={cn('size-3.5 rounded-full', DOT[column.status])}
                />
                <h3 id={id} className="flex-1 font-semibold">
                    {column.title}
                </h3>
                <span className="rounded-full bg-card px-2 text-sm text-muted-foreground">
                    {column.measures.length}
                </span>
            </div>
            <ul className="flex flex-col gap-2">
                {column.measures.map((measure) => (
                    <MeasureCard
                        key={measure.id}
                        workCenterId={workCenterId}
                        measure={measure}
                        canManage={canManage}
                    />
                ))}
            </ul>
        </section>
    );
}
