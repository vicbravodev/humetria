import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { growDelay } from '@/lib/chart';
import { cn } from '@/lib/utils';

export type WaterfallChartBar = {
    key: string;
    label: string;
    valueText: string;
    tooltip: string;
    /** Where it starts and ends, as fractions of the plot from its bottom. */
    from: number;
    to: number;
    total: boolean;
};

/** The thinnest a bar is drawn, so a small step still shows. */
const MIN_HEIGHT = 1.5;

/**
 * A cascade (board V4-Comp-Reporte): each step floats where the ones before it left the total and the last column is the
 * total, in a darker jade. Each column says its name and value below, and in a tooltip on hover and on focus.
 */
export default function WaterfallChart({
    label,
    bars,
}: {
    label: string;
    bars: WaterfallChartBar[];
}) {
    return (
        <figure className="flex flex-col gap-3">
            <figcaption className="sr-only">{label}</figcaption>
            <ol className="flex items-stretch gap-2 overflow-x-auto pb-1 sm:gap-3 print:overflow-visible">
                {bars.map((bar, index) => (
                    <li
                        key={bar.key}
                        className="flex min-w-[4.75rem] flex-1 flex-col gap-2"
                    >
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <span
                                    role="img"
                                    aria-label={bar.tooltip}
                                    tabIndex={0}
                                    className="block h-44 rounded-t-sm border-b outline-none focus-visible:ring-2 focus-visible:ring-ring sm:h-56"
                                >
                                    <svg
                                        aria-hidden
                                        viewBox="0 0 100 100"
                                        preserveAspectRatio="none"
                                        className={cn(
                                            'block size-full origin-bottom motion-safe:animate-report-grow-up',
                                            growDelay(index),
                                        )}
                                    >
                                        <rect
                                            x={8}
                                            width={84}
                                            y={(1 - bar.to) * 100}
                                            height={Math.max(
                                                (bar.to - bar.from) * 100,
                                                MIN_HEIGHT,
                                            )}
                                            className={
                                                bar.total
                                                    ? 'fill-chart-3'
                                                    : 'fill-primary'
                                            }
                                        />
                                    </svg>
                                </span>
                            </TooltipTrigger>
                            <TooltipContent>{bar.tooltip}</TooltipContent>
                        </Tooltip>
                        <span className="flex flex-col items-center text-center text-xs leading-4 sm:text-sm sm:leading-5">
                            <span
                                className={cn(
                                    'text-pretty [overflow-wrap:anywhere]',
                                    bar.total && 'font-semibold',
                                )}
                            >
                                {bar.label}
                            </span>
                            <span className="text-muted-foreground tabular-nums">
                                {bar.valueText}
                            </span>
                        </span>
                    </li>
                ))}
            </ol>
        </figure>
    );
}
