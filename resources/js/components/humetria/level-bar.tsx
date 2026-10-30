import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { percentText, shareOf } from '@/lib/results';
import { cn } from '@/lib/utils';
import type { LevelCounts, RiskLevelInfo, RiskLevelValue } from '@/types';

const FILLS: Record<RiskLevelValue, string> = {
    null: 'bg-risk-nulo',
    low: 'bg-risk-bajo',
    medium: 'bg-risk-medio',
    high: 'bg-risk-alto',
    very_high: 'bg-risk-muy-alto',
};

/**
 * A group's questionnaires by risk level as one bar (design system: LevelBar): the levels always from Nulo at the
 * left to Muy alto at the right, each segment as wide as its share, parted by a gap of the surface. The position
 * and the legend next to it carry the level; the color only confirms it. Hover tells each count.
 */
export default function LevelBar({
    counts,
    levels,
    label,
    className,
}: {
    counts: LevelCounts;
    levels: RiskLevelInfo[];
    label: string;
    className?: string;
}) {
    const total = counts.reduce((sum, count) => sum + count, 0);
    const description = levels
        .map((level, index) => `${level.label} ${counts[index] ?? 0}`)
        .join(', ');

    return (
        <div
            role="img"
            aria-label={`${label}: ${description}`}
            className={cn(
                'flex h-5 w-full gap-0.5 overflow-hidden rounded-[4px]',
                className,
            )}
        >
            {levels.map((level, index) => {
                const count = counts[index] ?? 0;

                if (count === 0) {
                    return null;
                }

                return (
                    <Tooltip key={level.value}>
                        <TooltipTrigger asChild>
                            <span
                                className={cn(
                                    'block h-full min-w-0.5 [-webkit-print-color-adjust:exact] [print-color-adjust:exact]',
                                    FILLS[level.value],
                                )}
                                style={{ width: `${shareOf(count, total)}%` }}
                            />
                        </TooltipTrigger>
                        <TooltipContent>
                            {level.label}: {count} ({percentText(count, total)})
                        </TooltipContent>
                    </Tooltip>
                );
            })}
        </div>
    );
}
