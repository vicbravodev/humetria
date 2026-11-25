import { useCountUp } from '@/hooks/use-count-up';
import { figureText } from '@/lib/compensation/report';
import type { SummaryFigure } from '@/lib/compensation/report';
import { growDelay } from '@/lib/chart';
import { cn } from '@/lib/utils';

/** One anchor of the summary: it counts up once as the report opens; the first, the cost of the first year, leads in the accent. */
export default function ReportFigure({
    figure,
    index,
}: {
    figure: SummaryFigure;
    index: number;
}) {
    const shown = useCountUp(figure.value);
    const lead = index === 0;

    return (
        <div
            className={cn(
                'flex break-inside-avoid flex-col gap-1.5 bg-card p-5 motion-safe:animate-report-rise',
                growDelay(index),
            )}
        >
            <dt className="text-caption font-semibold tracking-[0.06em] text-muted-foreground uppercase">
                {figure.caption}
            </dt>
            <dd
                className={cn(
                    'text-[28px] leading-9 font-semibold tracking-[-0.015em] tabular-nums',
                    lead && 'text-primary',
                )}
            >
                {/* On paper the final figure shows from the start: the count is for the screen only. */}
                <span className="print:hidden">
                    {figureText(figure.unit, shown)}
                </span>
                <span className="hidden print:inline">
                    {figureText(figure.unit, figure.value)}
                </span>
            </dd>
            <dd className="text-sm text-muted-foreground">{figure.note}</dd>
        </div>
    );
}
