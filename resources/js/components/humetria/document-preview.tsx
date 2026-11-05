import type { ReactNode } from 'react';

/**
 * A document on paper while it is written (boards V2-Politica and V3-Doc-Riesgos): who it is for, its title and, under a
 * rule, its sections.
 */
export default function DocumentPreview({
    owner,
    title,
    children,
}: {
    owner: string;
    title: string;
    children: ReactNode;
}) {
    return (
        <article
            aria-label="Vista previa del documento"
            className="flex flex-col gap-[18px] rounded-md bg-card px-6 py-8 text-meta leading-6 shadow-float md:px-11 md:py-10"
        >
            <div className="flex flex-col gap-1 border-b pb-3.5">
                <p className="text-caption font-medium text-muted-foreground">
                    {owner}
                </p>
                <h2 className="text-[22px] leading-[30px] font-semibold">
                    {title}
                </h2>
            </div>
            {children}
        </article>
    );
}
