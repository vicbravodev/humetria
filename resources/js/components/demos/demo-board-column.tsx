import { Link } from '@inertiajs/react';
import DemoBoardCard from '@/components/demos/demo-board-card';
import type { DemoBoardColumn as Column } from '@/types';

/** One open stage of the funnel: its total after the filters, its newest requests and the way to all of them in the list. */
export default function DemoBoardColumn({
    column,
    moreHref,
    chosen,
}: {
    column: Column;
    /** The list of this stage, with the same filters. */
    moreHref: string;
    chosen: { id: string | undefined; choose: (id: string) => void };
}) {
    const hidden = column.total - column.cards.length;

    return (
        <section
            aria-label={column.label}
            className="flex flex-col gap-2.5 rounded-[10px] bg-muted p-3"
        >
            <h2 className="flex justify-between px-1 text-sm font-semibold">
                {column.label}
                <span className="font-normal text-muted-foreground tabular-nums">
                    {column.total}
                </span>
            </h2>
            {column.cards.length === 0 ? (
                <p className="px-1 text-sm text-muted-foreground">
                    Ninguna por ahora.
                </p>
            ) : (
                <ul className="flex flex-col gap-2.5">
                    {column.cards.map((card) => (
                        <DemoBoardCard
                            key={card.id}
                            request={card}
                            chosen={card.id === chosen.id}
                            onChoose={() => chosen.choose(card.id)}
                        />
                    ))}
                </ul>
            )}
            {hidden > 0 && (
                <Link
                    href={moreHref}
                    className="inline-flex min-h-11 w-fit items-center px-1 text-sm font-medium text-primary underline-offset-4 hover:underline"
                >
                    Ver todas ({column.total})
                </Link>
            )}
        </section>
    );
}
