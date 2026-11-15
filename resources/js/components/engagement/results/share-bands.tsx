import { cn } from '@/lib/utils';
import type { EngagementItemDetail } from '@/types';

export const SHARE_BANDS = [
    { key: 'unfavorable', label: 'En desacuerdo', fill: 'bg-risk-alto' },
    {
        key: 'neutral',
        label: 'Ni de acuerdo ni en desacuerdo',
        fill: 'bg-chart-4',
    },
    { key: 'favorable', label: 'De acuerdo', fill: 'bg-primary' },
] as const;

/** How the answers to one question split in three, from disagreement to agreement; the legend is fixed over the list. */
export default function ShareBands({ item }: { item: EngagementItemDetail }) {
    return (
        <div
            role="img"
            aria-label={SHARE_BANDS.map(
                (band) => `${band.label} ${item[band.key] ?? 0} %`,
            ).join(', ')}
            className="flex h-1.5 w-full gap-0.5 overflow-hidden rounded-full"
        >
            {SHARE_BANDS.map((band) => (
                <span
                    key={band.key}
                    className={cn('block h-full', band.fill)}
                    style={{ width: `${item[band.key] ?? 0}%` }}
                />
            ))}
        </div>
    );
}
