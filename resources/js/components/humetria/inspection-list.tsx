import type { ReactNode } from 'react';

/** «Te falta» or «Lo puedes mostrar» (board V3-Inspeccion): a card with how many of the rows, and each row inside. */
export default function InspectionList({
    id,
    title,
    count,
    applicable,
    empty,
    children,
}: {
    id: string;
    title: string;
    count: number;
    applicable: number;
    empty: string;
    children: ReactNode;
}) {
    return (
        <section
            aria-labelledby={id}
            className="@container overflow-hidden card"
        >
            <div className="flex items-center justify-between gap-4 bg-muted px-5 py-3.5">
                <h2 id={id} className="font-semibold">
                    {title}
                </h2>
                <span className="text-sm text-muted-foreground">
                    {count} de {applicable} renglones
                </span>
            </div>
            {count === 0 ? (
                <p className="border-t px-5 py-4 text-muted-foreground">
                    {empty}
                </p>
            ) : (
                <ul>{children}</ul>
            )}
        </section>
    );
}
