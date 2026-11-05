import { useCountUp } from '@/hooks/use-count-up';
import { figureText } from '@/lib/compensation/report';
import type { SummaryFigure } from '@/lib/compensation/report';
import { growDelay } from '@/lib/chart';
import { cn } from '@/lib/utils';

/** One figure of the summary: it counts up once as the report opens; the first, the cost of the first year, leads in the accent. */
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
                'flex break-inside-avoid flex-col gap-1.5 p-5 motion-safe:animate-report-rise',
                growDelay(index),
                lead ? 'rounded-xl bg-accent text-accent-foreground' : 'card',
            )}
        >
            <dt
                className={cn(
                    'text-caption font-semibold tracking-[0.06em] uppercase',
                    lead ? 'text-primary' : 'text-muted-foreground',
                )}
            >
                {figure.caption}
            </dt>
            <dd
                className={cn(
                    'font-semibold tabular-nums',
                    lead
                        ? 'text-[34px] leading-10 tracking-[-0.02em]'
                        : 'text-[26px] leading-8 tracking-[-0.01em]',
                )}
            >
                {figureText(figure.unit, shown)}
            </dd>
            <dd
                className={cn(
                    'text-sm',
                    lead ? 'text-foreground' : 'text-muted-foreground',
                )}
            >
                {figure.note}
            </dd>
        </div>
    );
}
