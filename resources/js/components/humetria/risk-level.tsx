import { levelSegments, RISK_LEVELS } from '@/lib/results';
import { cn } from '@/lib/utils';
import type { RiskLevelValue } from '@/types';

const TONES: Record<RiskLevelValue, { fill: string; label: string }> = {
    null: { fill: 'bg-risk-nulo', label: 'bg-risk-nulo-bg text-risk-nulo-fg' },
    low: { fill: 'bg-risk-bajo', label: 'bg-risk-bajo-bg text-risk-bajo-fg' },
    medium: {
        fill: 'bg-risk-medio',
        label: 'bg-risk-medio-bg text-risk-medio-fg',
    },
    high: { fill: 'bg-risk-alto', label: 'bg-risk-alto-bg text-risk-alto-fg' },
    very_high: {
        fill: 'bg-risk-muy-alto',
        label: 'bg-risk-muy-alto-bg text-risk-muy-alto-fg',
    },
};

/**
 * A risk level (design system: RiskLevel): a meter of five segments, the written label and its color, so nobody
 * depends on the color alone. The meter is decoration for the label next to it.
 */
export default function RiskLevel({
    level,
    label,
}: {
    level: RiskLevelValue;
    label: string;
}) {
    const filled = levelSegments(level);
    const tone = TONES[level];

    return (
        <span className="inline-flex items-center gap-3">
            <span aria-hidden="true" className="flex gap-1">
                {RISK_LEVELS.map((value, index) => (
                    <span
                        key={value}
                        className={cn(
                            'h-3.5 w-2 rounded-sm',
                            index < filled ? tone.fill : 'bg-muted',
                        )}
                    />
                ))}
            </span>
            <span
                className={cn(
                    'rounded-md px-2 py-0.5 text-sm font-semibold whitespace-nowrap',
                    tone.label,
                )}
            >
                {label}
            </span>
        </span>
    );
}
