import { CircleAlert } from 'lucide-react';
import type { ReactNode } from 'react';
import { pendingText } from '@/lib/report-findings';
import type { NomReport } from '@/types';

/** What HR still has to fill in before handing the report over, and where to do it. Never printed. */
export default function ReportPending({
    report,
    children,
}: {
    report: NomReport;
    /** The way to fill it in, or who can. */
    children: ReactNode;
}) {
    const text = pendingText(report);

    if (text === null) {
        return null;
    }

    const [lead, parts] = text.split(': ');

    return (
        <div className="flex flex-wrap items-center justify-between gap-4 rounded-lg bg-risk-medio-bg py-3.5 pr-4 pl-5 text-risk-medio-fg motion-safe:animate-report-rise print:hidden">
            <p className="flex items-center gap-3 text-[15px] leading-[22px]">
                <CircleAlert aria-hidden="true" className="size-5 shrink-0" />
                <span>
                    <b className="font-semibold">{lead}:</b> {parts}
                </span>
            </p>
            {children}
        </div>
    );
}
