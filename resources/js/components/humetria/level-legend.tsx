import { percentText } from '@/lib/results';
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
    const total = counts?.reduce((sum, count) => sum + count, 0) ?? 0;

    return (
        <ul className={cn('flex flex-wrap gap-x-5 gap-y-2 text-sm', className)}>
            {levels.map((level, index) => {
                const count = counts?.[index];

                return (
                    <li key={level.value} className="flex items-center gap-2">
                        <span
                            aria-hidden="true"
                            className={cn(
                                'size-2.5 shrink-0 rounded-[3px] [-webkit-print-color-adjust:exact] [print-color-adjust:exact]',
                                SWATCHES[level.value],
                            )}
                        />
                        <span>{level.label}</span>
                        {count !== undefined && (
                            <span className="text-muted-foreground tabular-nums">
                                {count} · {percentText(count, total)}
                            </span>
                        )}
                    </li>
                );
            })}
        </ul>
    );
}
