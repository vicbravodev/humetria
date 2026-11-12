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
    baseline,
}: {
    label: string;
    columns: ColumnChartColumn[];
    /** A dashed line across the columns (the first measurement, the company's average), as high as `percent`. */
    baseline?: { percent: number; label: string };
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
                                    className="relative block h-40 w-full max-w-28 border-b outline-none focus-visible:ring-2 focus-visible:ring-ring sm:h-48"
                                >
                                    {baseline !== undefined && (
                                        <span
                                            aria-hidden
                                            data-slot="column-baseline"
                                            className="absolute inset-x-0 z-10 border-t-2 border-dashed border-foreground"
                                            style={{
                                                bottom: `${baseline.percent}%`,
                                            }}
                                        />
                                    )}
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
            {baseline !== undefined && (
                <p className="mt-3 flex items-center gap-2 text-sm text-muted-foreground">
                    <span
                        aria-hidden
                        className="w-5 border-t-2 border-dashed border-foreground"
                    />
                    {baseline.label}
                </p>
            )}
        </figure>
    );
}
