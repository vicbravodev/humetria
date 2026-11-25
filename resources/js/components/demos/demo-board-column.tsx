import { Link } from '@inertiajs/react';
import DemoBoardCard from '@/components/demos/demo-board-card';
import { index } from '@/routes/demo-requests';
import type { DemoBoardColumn as Column } from '@/types';

/** One open stage of the funnel: its total, its newest requests and the way to all of them in the list. */
export default function DemoBoardColumn({
    column,
    chosenId,
    onChoose,
}: {
    column: Column;
    chosenId: string | undefined;
    onChoose: (id: string) => void;
}) {
    const hidden = column.total - column.cards.length;

    return (
        <section
            aria-label={column.label}
            className="flex flex-col gap-2.5 rounded-[10px] bg-muted p-3"
        >
            <h3 className="flex justify-between px-1 text-sm font-semibold">
                {column.label}
                <span className="font-normal text-muted-foreground tabular-nums">
                    {column.total}
                </span>
            </h3>
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
                            chosen={card.id === chosenId}
                            onChoose={() => onChoose(card.id)}
                        />
                    ))}
                </ul>
            )}
            {hidden > 0 && (
                <Link
                    href={index.url({ query: { status: column.status } })}
                    className="inline-flex min-h-11 w-fit items-center px-1 text-sm font-medium text-primary underline-offset-4 hover:underline"
                >
                    Ver todas ({column.total})
                </Link>
            )}
        </section>
    );
}
