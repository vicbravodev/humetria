import LevelBar from '@/components/humetria/level-bar';
import LevelLegend from '@/components/humetria/level-legend';
import { areaBuckets, highTone } from '@/lib/report';
import { highRisk, shareOf } from '@/lib/results';
import { cn } from '@/lib/utils';
import type { AreaResult, RiskLevelInfo } from '@/types';

/** The final score of an area in three levels, so that no small group stands out: Nulo o bajo, Medio, Alto o muy alto. */
const BUCKETS: RiskLevelInfo[] = [
    { value: 'low', label: 'Nulo o bajo', action: '' },
    { value: 'medium', label: 'Medio', action: '' },
    { value: 'very_high', label: 'Alto o muy alto', action: '' },
];

const TONES = {
    very_high: 'text-risk-muy-alto-fg',
    high: 'text-risk-alto-fg',
};

export default function AreaCard({
    area,
    index,
}: {
    area: AreaResult;
    index: number;
}) {
    const share = Math.round(shareOf(highRisk(area.final), area.answered));
    const tone = highTone(share);

    return (
        <div
            className="flex break-inside-avoid flex-col gap-4 rounded-lg border bg-card p-6 motion-safe:animate-report-rise"
            style={{ animationDelay: `${index * 100}ms` }}
        >
            <div className="flex items-baseline justify-between gap-3">
                <div className="flex flex-col gap-0.5">
                    <h3 className="text-xl leading-7 font-semibold">
                        {area.name}
                    </h3>
                    <span className="text-sm text-muted-foreground">
                        {area.answered} cuestionarios
                    </span>
                </div>
                <div className="flex flex-col items-end">
                    <span
                        className={cn(
                            'text-[32px] leading-9 font-semibold tracking-[-0.01em] tabular-nums',
                            tone !== null && TONES[tone],
                        )}
                    >
                        {share} %
                    </span>
                    <span className="text-[13px] text-muted-foreground">
                        alto o muy alto
                    </span>
                </div>
            </div>
            <div className="origin-left motion-safe:animate-report-grow">
                <LevelBar
                    counts={areaBuckets(area.final)}
                    levels={BUCKETS}
                    label={area.name}
                />
            </div>
            <LevelLegend
                levels={BUCKETS}
                counts={areaBuckets(area.final)}
                className="gap-x-4 gap-y-1.5 text-[13px]"
            />
        </div>
    );
}
