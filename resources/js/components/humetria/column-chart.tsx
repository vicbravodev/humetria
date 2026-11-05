import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { growDelay } from '@/lib/chart';
import { cn } from '@/lib/utils';

export type ColumnChartColumn = {
    key: string;
    label: string;
    valueText: string;
    tooltip: string;
    /** Its height against the tallest, 0 to 100. */
    percent: number;
};

/**
 * Columns on a common scale (board V4-Comp-Reporte, «Los próximos años»): the value on top, the name below, in the one jade of
 * the charts, and what each is in a tooltip on hover and on focus. They grow once from the axis as the page opens.
 */
export default function ColumnChart({
    label,
    columns,
}: {
    label: string;
    columns: ColumnChartColumn[];
}) {
    return (
        <figure>
            <figcaption className="sr-only">{label}</figcaption>
            <ol className="flex items-stretch gap-3 sm:gap-6">
                {columns.map((column, index) => (
                    <li
                        key={column.key}
                        className="flex min-w-0 flex-1 flex-col items-center gap-2"
                    >
                        <span className="text-sm font-semibold tabular-nums sm:text-base">
                            {column.valueText}
                        </span>
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <span
                                    role="img"
                                    aria-label={column.tooltip}
                                    tabIndex={0}
                                    className="block h-40 w-full max-w-28 border-b outline-none focus-visible:ring-2 focus-visible:ring-ring sm:h-48"
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
                                            y={100 - column.percent}
                                            width={100}
                                            height={column.percent}
                                            className="fill-primary"
                                        />
                                    </svg>
                                </span>
                            </TooltipTrigger>
                            <TooltipContent>{column.tooltip}</TooltipContent>
                        </Tooltip>
                        <span className="text-sm text-muted-foreground">
                            {column.label}
                        </span>
                    </li>
                ))}
            </ol>
        </figure>
    );
}
