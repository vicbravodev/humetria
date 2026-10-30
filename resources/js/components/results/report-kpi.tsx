import type { ReactNode } from 'react';
import ReportCaption from '@/components/results/report-caption';
import { useCountUp } from '@/hooks/use-count-up';
import { shareOf } from '@/lib/results';
import { cn } from '@/lib/utils';

/** One figure of the summary (design system: KpiCard): a share, its bar, and the counts behind it. */
export default function ReportKpi({
    caption,
    value,
    of,
    tone,
    delay,
    children,
}: {
    caption: string;
    value: number;
    of: number;
    /** Primary for participation; risk for a share at risk, alarm when the figure itself is written in its tone. */
    tone: 'primary' | 'risk' | 'alarm';
    /** Its place in the row, for the entrance one after another. */
    delay: number;
    children: ReactNode;
}) {
    const share = shareOf(value, of);
    const shown = useCountUp(Math.round(share));

    return (
        <div
            className="flex break-inside-avoid flex-col gap-2 rounded-lg border bg-card p-6 motion-safe:animate-report-rise"
            style={{ animationDelay: `${80 * (delay + 1)}ms` }}
        >
            <ReportCaption>{caption}</ReportCaption>
            <p
                className={cn(
                    'text-[44px] leading-[48px] font-semibold tracking-[-0.02em] tabular-nums',
                    tone === 'alarm' && 'text-risk-muy-alto-fg',
                )}
            >
                {shown} %
            </p>
            <div className="h-1.5 overflow-hidden rounded-full bg-border">
                <div
                    className={cn(
                        'h-full origin-left rounded-full motion-safe:animate-report-grow',
                        tone === 'primary' ? 'bg-primary' : 'bg-risk-muy-alto',
                    )}
                    style={{
                        width: `${share}%`,
                        animationDelay: `${200 + 80 * delay}ms`,
                    }}
                />
            </div>
            <p className="text-sm text-muted-foreground">{children}</p>
        </div>
    );
}
