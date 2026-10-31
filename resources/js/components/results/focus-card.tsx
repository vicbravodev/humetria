import LevelBar from '@/components/humetria/level-bar';
import RiskLevel from '@/components/humetria/risk-level';
import { domainDescription, mostFrequentLevel } from '@/lib/report';
import { highRisk, percentText, RISK_LEVELS } from '@/lib/results';
import { cn } from '@/lib/utils';
import type { RiskGroup, RiskLevelInfo } from '@/types';

/** A domain to act on first: its rank, what it looks at, its share in Alto or Muy alto and its bar. */
export default function FocusCard({
    domain,
    category,
    rank,
    levels,
}: {
    domain: RiskGroup;
    category: string;
    rank: number;
    levels: RiskLevelInfo[];
}) {
    const answered = domain.levels.reduce((sum, count) => sum + count, 0);
    const high = highRisk(domain.levels);
    const frequent = mostFrequentLevel(domain.levels);

    return (
        <article
            className={cn(
                'flex break-inside-avoid flex-col gap-4 rounded-lg border bg-card p-6 motion-safe:animate-report-rise',
                rank === 1 && 'border-t-[3px] border-t-risk-muy-alto',
            )}
            style={{ animationDelay: `${(rank - 1) * 100}ms` }}
        >
            <div className="flex items-start justify-between gap-3">
                <span className="text-[13px] font-semibold text-muted-foreground">
                    #{rank}
                </span>
                <RiskLevel
                    level={RISK_LEVELS[frequent] ?? 'very_high'}
                    label={`Nivel más frecuente: ${levels[frequent]?.label ?? ''}`}
                />
            </div>
            <div className="flex min-h-[92px] flex-col gap-1">
                <h3 className="text-xl leading-7 font-semibold">
                    {domain.label}
                </h3>
                <span className="text-[13px] text-muted-foreground">
                    {category}
                </span>
                <span className="mt-1 text-sm text-muted-foreground">
                    {domainDescription(domain.key)}
                </span>
            </div>
            <p className="flex items-baseline gap-2.5">
                <span className="text-[40px] leading-[44px] font-semibold tracking-[-0.02em] text-risk-muy-alto-fg tabular-nums">
                    {percentText(high, answered)}
                </span>
                <span className="text-sm text-muted-foreground">
                    {high} de {answered} · {domain.levels[4] ?? 0} en muy alto
                </span>
            </p>
            <div
                className="origin-left motion-safe:animate-report-grow"
                style={{ animationDelay: `${200 + (rank - 1) * 120}ms` }}
            >
                <LevelBar
                    counts={domain.levels}
                    levels={levels}
                    label={domain.label}
                    className="h-3.5"
                />
            </div>
        </article>
    );
}
