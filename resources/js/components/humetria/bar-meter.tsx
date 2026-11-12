import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { growDelay } from '@/lib/chart';
import { cn } from '@/lib/utils';

/**
 * One horizontal bar of a chart (board V4-Comp-Reporte): its length on a common scale, in the one jade of the charts or muted
 * when it is not the one that matters, and what it is in a tooltip on hover and on focus. It grows once from its axis as the
 * page opens (never with reduced motion nor in print).
 */
export default function BarMeter({
    percent,
    tooltip,
    muted = false,
    index = 0,
    reference,
    className,
}: {
    /** 0 to 100. */
    percent: number;
    tooltip: string;
    muted?: boolean;
    /** Its place in the chart, so the bars grow one after another. */
    index?: number;
    /** Where the reference sits (the company's average, the last wave), 0 to 100: a vertical mark across the bar. */
    reference?: number;
    className?: string;
}) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <span
                    role="img"
                    aria-label={tooltip}
                    tabIndex={0}
                    className={cn(
                        'relative block h-2.5 rounded-full outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background',
                        className,
                    )}
                >
                    <span className="block size-full overflow-hidden rounded-full bg-track">
                        <svg
                            aria-hidden
                            viewBox="0 0 100 1"
                            preserveAspectRatio="none"
                            className={cn(
                                'block size-full origin-left motion-safe:animate-report-grow',
                                growDelay(index),
                            )}
                        >
                            <rect
                                width={percent}
                                height={1}
                                className={
                                    muted
                                        ? 'fill-muted-foreground'
                                        : 'fill-primary'
                                }
                            />
                        </svg>
                    </span>
                    {reference !== undefined && (
                        <span
                            aria-hidden
                            data-slot="bar-reference"
                            className="absolute -top-1 -bottom-1 w-0.5 -translate-x-1/2 rounded-full bg-foreground"
                            style={{ left: `${reference}%` }}
                        />
                    )}
                </span>
            </TooltipTrigger>
            <TooltipContent>{tooltip}</TooltipContent>
        </Tooltip>
    );
}
