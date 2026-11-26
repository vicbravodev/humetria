import type { ReactNode } from 'react';

/** One line of the request's panel (tablero Demos): what it is at the left, its value at the right. */
export default function DemoFactRow({
    term,
    children,
}: {
    term: string;
    children: ReactNode;
}) {
    return (
        <div className="flex items-start gap-3 border-t px-5 py-3.5 first:border-t-0 md:px-6">
            <dt className="shrink-0 text-muted-foreground">{term}</dt>
            <dd className="min-w-0 flex-1 text-right font-medium break-words">
                {children}
            </dd>
        </div>
    );
}
