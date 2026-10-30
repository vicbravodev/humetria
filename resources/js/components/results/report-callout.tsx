import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import ReportCaption from '@/components/results/report-caption';
import { cn } from '@/lib/utils';

/** A fact that frames the foci: the area most exposed, in terracotta, or the strength, in jade. */
export default function ReportCallout({
    icon: Icon,
    caption,
    tone,
    children,
}: {
    icon: LucideIcon;
    caption: string;
    tone: 'risk' | 'strength';
    children: ReactNode;
}) {
    return (
        <div className="flex break-inside-avoid items-center gap-4 rounded-lg border px-6 py-5">
            <div
                className={cn(
                    'flex size-11 shrink-0 items-center justify-center rounded-lg',
                    tone === 'risk'
                        ? 'bg-risk-muy-alto-bg text-risk-muy-alto-fg'
                        : 'bg-risk-bajo-bg text-risk-bajo-fg',
                )}
            >
                <Icon aria-hidden="true" className="size-[22px]" />
            </div>
            <div className="flex flex-col gap-0.5">
                <ReportCaption>{caption}</ReportCaption>
                <p>{children}</p>
            </div>
        </div>
    );
}
