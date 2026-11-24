import type { ReactNode } from 'react';

/** The frame of a diagram in an article: a title that says what it shows, the drawing, and where its numbers come from. */
export default function DiagramFrame({
    title,
    source,
    children,
}: {
    title: string;
    source: string;
    children: ReactNode;
}) {
    return (
        <figure className="my-8 overflow-hidden card p-5 sm:p-6">
            <figcaption className="text-meta font-semibold text-balance">
                {title}
            </figcaption>
            <div className="mt-5">{children}</div>
            <p className="mt-5 text-caption text-muted-foreground">{source}</p>
        </figure>
    );
}
