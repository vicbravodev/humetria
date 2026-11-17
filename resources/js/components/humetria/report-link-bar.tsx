import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * The bar on top of a report opened from a temporary link (board ReporteCompartido): 60 px, what identifies it (the logo) at the
 * left and what to do with it (print) at the right. Whoever opens it has no account, so there is nothing else to offer. It is
 * not printed.
 */
export default function ReportLinkBar({
    start,
    className,
    children,
}: {
    start: ReactNode;
    /** The width the report under it has, so both line up on a wide screen. */
    className?: string;
    children: ReactNode;
}) {
    return (
        <header
            data-slot="report-link-bar"
            className="h-[60px] border-b bg-card print:hidden"
        >
            <div
                className={cn(
                    'mx-auto flex h-full items-center justify-between gap-3 px-4',
                    className,
                )}
            >
                {start}
                {children}
            </div>
        </header>
    );
}
