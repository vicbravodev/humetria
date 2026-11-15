import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { signedColumnsOf } from '@/lib/engagement/trend';
import type { TrendPoint } from '@/types';

/**
 * The recommendation index across the waves: columns that rise from zero or hang from it, on its own axis of −100 to
 * 100 rounded to fifty, the value above and the month below.
 */
export default function SignedColumns({
    label,
    points,
}: {
    label: string;
    points: TrendPoint[];
}) {
    const chart = signedColumnsOf(points);

    return (
        <figure>
            <figcaption className="sr-only">{label}</figcaption>
            <ol className="flex items-stretch gap-2 sm:gap-4">
                {chart.columns.map((column) => (
                    <li
                        key={column.key}
                        className="flex min-w-0 flex-1 flex-col items-center gap-2"
                    >
                        <span className="text-sm font-semibold tabular-nums">
                            {column.valueText}
                        </span>
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <span
                                    role="img"
                                    aria-label={column.tooltip}
                                    tabIndex={0}
                                    className="relative block h-36 w-full max-w-20 outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                >
                                    <span
                                        aria-hidden
                                        className="absolute inset-x-0 border-t border-foreground"
                                        style={{ top: `${chart.zero}%` }}
                                    />
                                    <span
                                        aria-hidden
                                        className="absolute inset-x-0 rounded-[3px] bg-primary"
                                        style={{
                                            top: `${column.top}%`,
                                            height: `${column.height}%`,
                                        }}
                                    />
                                </span>
                            </TooltipTrigger>
                            <TooltipContent>{column.tooltip}</TooltipContent>
                        </Tooltip>
                        <span className="text-center text-sm text-muted-foreground">
                            {column.label}
                        </span>
                    </li>
                ))}
            </ol>
        </figure>
    );
}
