import LevelBar from '@/components/humetria/level-bar';
import { highTone, sideShares } from '@/lib/report';
import { cn } from '@/lib/utils';
import type { RiskGroup, RiskLevelInfo } from '@/types';

const TONES = {
    very_high: 'text-risk-muy-alto-fg',
    high: 'text-risk-alto-fg',
};

/**
 * A category or a domain on both sides of the axis: to the left, its questionnaires in Nulo to Medio; to the right,
 * those in Alto or Muy alto. Each side as long as its share, on a scale of `scale` % per side.
 */
export default function DivergingRow({
    group,
    levels,
    layout,
}: {
    group: RiskGroup;
    levels: RiskLevelInfo[];
    layout: { kind: 'category' | 'domain'; scale: number; index: number };
}) {
    const { low, high } = sideShares(group.levels);
    const category = layout.kind === 'category';
    const tone = highTone(Math.round(high));
    const delay = { animationDelay: `${120 + layout.index * 70}ms` };

    return (
        <div
            className={cn(
                'grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)_56px] items-center rounded-md hover:bg-accent md:grid-cols-[300px_minmax(0,1fr)_minmax(0,1fr)_72px]',
                category
                    ? 'mt-1.5 min-h-12 border-t pt-1.5 first:mt-0 first:border-t-0'
                    : 'min-h-10',
            )}
        >
            <span
                className={cn(
                    'col-span-full pr-4 pb-1.5 md:col-span-1 md:pb-0',
                    category
                        ? 'leading-[22px] font-semibold'
                        : 'pl-5 text-[15px] leading-5',
                )}
            >
                {group.label}
            </span>
            <div className="flex h-full min-h-10 items-center justify-end pr-0.5">
                <div
                    className="origin-right motion-safe:animate-report-grow"
                    style={{
                        width: `${(low / layout.scale) * 100}%`,
                        ...delay,
                    }}
                >
                    <LevelBar
                        counts={group.levels.slice(0, 3)}
                        levels={levels.slice(0, 3)}
                        label={`${group.label}, nulo a medio`}
                        className={cn(
                            'rounded-r-none',
                            category ? 'h-6' : 'h-4',
                        )}
                    />
                </div>
            </div>
            <div className="flex h-full min-h-10 items-center border-l-2 border-foreground pl-0.5">
                <div
                    className="origin-left motion-safe:animate-report-grow"
                    style={{
                        width: `${(high / layout.scale) * 100}%`,
                        ...delay,
                    }}
                >
                    <LevelBar
                        counts={group.levels.slice(3)}
                        levels={levels.slice(3)}
                        label={`${group.label}, alto o muy alto`}
                        className={cn(
                            'rounded-l-none',
                            category ? 'h-6' : 'h-4',
                        )}
                    />
                </div>
            </div>
            <span
                className={cn(
                    'text-right font-semibold tabular-nums',
                    category ? 'text-lg' : 'text-[15px]',
                    tone !== null && !category && TONES[tone],
                )}
            >
                {Math.round(high)} %
            </span>
        </div>
    );
}
