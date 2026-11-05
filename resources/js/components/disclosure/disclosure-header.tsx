import type { ReactNode } from 'react';

/**
 * The top of a page of the disclosure (boards V4-Difusion, V4-Difusion-Contenidos and V4-Difusion-Registro): the numerals it
 * answers to as an eyebrow, the title, what it is for in a sentence and, at its right, how far it goes.
 */
export default function DisclosureHeader({
    eyebrow,
    title,
    lead,
    children,
}: {
    eyebrow: string;
    title: string;
    lead: string;
    children?: ReactNode;
}) {
    return (
        <header className="flex flex-wrap items-end justify-between gap-x-10 gap-y-5">
            <div className="flex max-w-[760px] min-w-0 flex-col gap-2">
                <p className="text-caption font-semibold tracking-[0.06em] text-primary uppercase">
                    {eyebrow}
                </p>
                <h1 className="text-3xl leading-10 font-semibold tracking-[-0.015em] text-balance">
                    {title}
                </h1>
                <p className="text-lg text-pretty text-muted-foreground">
                    {lead}
                </p>
            </div>
            {children}
        </header>
    );
}
