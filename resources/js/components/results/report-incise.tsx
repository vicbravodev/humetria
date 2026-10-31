import type { ReactNode } from 'react';

/** One incise of num. 7.7: its letter, its title and what it says, or a mark while HR has not filled it in. */
export default function ReportIncise({
    code,
    title,
    pending = false,
    children,
}: {
    code: string;
    title: string;
    pending?: boolean;
    children: ReactNode;
}) {
    return (
        // On a phone the code goes above the text, which keeps the whole width to read.
        <div className="grid break-inside-avoid gap-x-4 gap-y-1 border-b px-4 py-5 last:border-b-0 sm:grid-cols-[56px_minmax(0,1fr)] sm:px-6">
            <span className="pt-0.5 text-sm font-semibold text-muted-foreground">
                7.7 {code}
            </span>
            <div className="flex flex-col gap-1.5">
                <div className="flex flex-wrap items-center justify-between gap-x-3 gap-y-2">
                    <h3 className="text-[17px] leading-6 font-semibold">
                        {title}
                    </h3>
                    {pending && (
                        <span className="rounded-md bg-risk-medio-bg px-2.5 py-[3px] text-[13px] font-medium text-risk-medio-fg">
                            Pendiente de capturar
                        </span>
                    )}
                </div>
                <div className="text-[15px] leading-[22px]">{children}</div>
            </div>
        </div>
    );
}
