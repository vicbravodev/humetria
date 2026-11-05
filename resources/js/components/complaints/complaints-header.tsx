import type { ReactNode } from 'react';

/**
 * The top of a page of the complaints channel (boards V4-Quejas-Canal, V4-Quejas-Bandeja and V4-Quejas-Caso): the numeral or
 * the kind of case as an eyebrow, the title with its state, a sentence of what it is, and its actions at the right.
 */
export default function ComplaintsHeader({
    eyebrow,
    title,
    status,
    lead,
    mono = false,
    children,
}: {
    eyebrow: string;
    title: string;
    status?: ReactNode;
    lead: ReactNode;
    /** A reference reads in the monospaced font, as the person wrote it down. */
    mono?: boolean;
    children?: ReactNode;
}) {
    return (
        <header className="flex flex-wrap items-end justify-between gap-x-10 gap-y-5">
            <div className="flex max-w-[760px] min-w-0 flex-col gap-2">
                <p className="text-caption font-semibold tracking-[0.06em] text-primary uppercase">
                    {eyebrow}
                </p>
                <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <h1
                        className={
                            mono
                                ? 'font-mono text-3xl leading-10 font-semibold tracking-[0.02em]'
                                : 'text-3xl leading-10 font-semibold tracking-[-0.015em] text-balance'
                        }
                    >
                        {title}
                    </h1>
                    {status}
                </div>
                <p className="text-lg text-pretty text-muted-foreground">
                    {lead}
                </p>
            </div>
            {children !== undefined && (
                <div className="flex flex-wrap items-center gap-2 max-sm:w-full print:hidden">
                    {children}
                </div>
            )}
        </header>
    );
}
