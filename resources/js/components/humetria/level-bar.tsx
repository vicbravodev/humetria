import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { percentText, shareOf, total } from '@/lib/results';
import { riskFill } from '@/lib/risk-tones';
import { cn } from '@/lib/utils';
import type { LevelCounts, RiskLevelInfo } from '@/types';

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
    const answered = total(counts);
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
                                    riskFill(level.value),
                                )}
                                style={{
                                    width: `${shareOf(count, answered)}%`,
                                }}
                            />
                        </TooltipTrigger>
                        <TooltipContent>
                            {level.label}: {count} (
                            {percentText(count, answered)})
                        </TooltipContent>
                    </Tooltip>
                );
            })}
        </div>
    );
}
