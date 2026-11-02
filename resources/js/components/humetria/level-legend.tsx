import { riskFill } from '@/lib/risk-tones';
import { cn } from '@/lib/utils';
import type { LevelCounts, RiskLevelInfo } from '@/types';

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
                'flex flex-wrap gap-x-4 gap-y-1.5 text-caption',
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
                                riskFill(level.value),
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
