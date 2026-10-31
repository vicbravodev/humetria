import { cn } from '@/lib/utils';
import type { LevelCounts, RiskLevelInfo, RiskLevelValue } from '@/types';

const SWATCHES: Record<RiskLevelValue, string> = {
    null: 'bg-risk-nulo',
    low: 'bg-risk-bajo',
    medium: 'bg-risk-medio',
    high: 'bg-risk-alto',
    very_high: 'bg-risk-muy-alto',
};

/**
 * What each color of a LevelBar means, in the bar's order, and the count behind each level when there is one group.
 */
export default function LevelLegend({
    levels,
    counts,
    className,
}: {
    levels: RiskLevelInfo[];
    /** The counts of the one group the legend describes; without them it only names the levels. */
    counts?: LevelCounts;
    className?: string;
}) {
    return (
        <ul
            className={cn(
                'flex flex-wrap gap-x-4 gap-y-1.5 text-[13px] leading-[18px]',
                className,
            )}
        >
            {levels.map((level, index) => {
                const count = counts?.[index];

                return (
                    <li key={level.value} className="flex items-center gap-1.5">
                        <span
                            aria-hidden="true"
                            className={cn(
                                'size-2 shrink-0 rounded-[2px] [-webkit-print-color-adjust:exact] [print-color-adjust:exact]',
                                SWATCHES[level.value],
                            )}
                        />
                        <span className="text-muted-foreground">
                            {level.label}
                        </span>
                        {count !== undefined && (
                            <span className="font-semibold tabular-nums">
                                {count}
                            </span>
                        )}
                    </li>
                );
            })}
        </ul>
    );
}
