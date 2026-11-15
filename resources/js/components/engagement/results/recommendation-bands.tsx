import { cn } from '@/lib/utils';
import type { EngagementBands } from '@/types';

const BANDS = [
    {
        key: 'detractors',
        label: 'no lo recomendarían (0 a 6)',
        fill: 'bg-risk-alto',
    },
    { key: 'passives', label: 'indiferentes (7 y 8)', fill: 'bg-chart-4' },
    {
        key: 'promoters',
        label: 'lo recomendarían (9 y 10)',
        fill: 'bg-primary',
    },
] as const;

/** The three shares behind the recommendation index as one bar of three stretches, with a legend that names each. */
export default function RecommendationBands({
    bands,
}: {
    bands: EngagementBands;
}) {
    return (
        <div className="flex flex-col gap-3">
            <div
                role="img"
                aria-label={BANDS.map(
                    (band) => `${bands[band.key]} % ${band.label}`,
                ).join(', ')}
                className="flex h-3.5 w-full gap-0.5 overflow-hidden rounded-full"
            >
                {BANDS.map((band) => (
                    <span
                        key={band.key}
                        className={cn('block h-full min-w-0.5', band.fill)}
                        style={{ width: `${bands[band.key]}%` }}
                    />
                ))}
            </div>
            <ul className="grid gap-x-4 gap-y-1.5 text-sm sm:grid-cols-3">
                {BANDS.map((band) => (
                    <li key={band.key} className="flex items-start gap-2">
                        <span
                            aria-hidden
                            className={cn(
                                'mt-1.5 size-2.5 shrink-0 rounded-[3px]',
                                band.fill,
                            )}
                        />
                        <span>
                            <strong className="font-semibold tabular-nums">
                                {bands[band.key]} %
                            </strong>{' '}
                            {band.label}
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}
