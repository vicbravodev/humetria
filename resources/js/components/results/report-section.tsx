import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/** A part of the report: its anchor for the bar, its title, a note at the right and what it is about. */
export default function ReportSection({
    id,
    title,
    aside,
    description,
    pageBreak = false,
    children,
}: {
    id: string;
    title: string;
    aside?: string;
    description?: string;
    /** Starts a new page when printed. */
    pageBreak?: boolean;
    children: ReactNode;
}) {
    return (
        <section
            id={id}
            className={cn(
                'flex scroll-mt-40 flex-col gap-6',
                pageBreak && 'print:break-before-page',
            )}
        >
            <div className="flex flex-col gap-1.5">
                <div className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                    <h2 className="text-[28px] leading-9 font-semibold tracking-[-0.01em]">
                        {title}
                    </h2>
                    {aside !== undefined && (
                        <span className="text-[13px] text-muted-foreground">
                            {aside}
                        </span>
                    )}
                </div>
                {description !== undefined && (
                    <p className="max-w-[760px] text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {children}
        </section>
    );
}
