import { Info } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/** A part of the report (board V4-Comp-Reporte): its anchor for the tabs, its numbered title, what it shows and how to read it. */
export default function ReportPart({
    id,
    title,
    description,
    hint,
    pageBreak = false,
    children,
}: {
    id: string;
    title: string;
    description?: string;
    /** «Pasa el cursor sobre una barra»: how to read a chart; not printed. */
    hint?: string;
    /** Starts a new page when printed. */
    pageBreak?: boolean;
    children: ReactNode;
}) {
    return (
        <section
            id={id}
            aria-labelledby={`${id}-title`}
            className={cn(
                'flex scroll-mt-32 flex-col gap-5',
                pageBreak && 'print:break-before-page',
            )}
        >
            <div className="flex flex-wrap items-end justify-between gap-x-6 gap-y-2">
                <div className="flex max-w-[760px] flex-col gap-1">
                    <h2
                        id={`${id}-title`}
                        className="text-xl leading-7 font-semibold tracking-[-0.005em]"
                    >
                        {title}
                    </h2>
                    {description !== undefined && (
                        <p className="text-muted-foreground">{description}</p>
                    )}
                </div>
                {hint !== undefined && (
                    <span className="hidden items-center gap-1.5 rounded-full border bg-muted px-2.5 py-0.5 text-caption text-muted-foreground sm:inline-flex print:hidden">
                        <Info aria-hidden className="size-3.5" />
                        {hint}
                    </span>
                )}
            </div>
            {children}
        </section>
    );
}
