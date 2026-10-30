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
 * A risk level (design system: RiskLevel): a meter of five segments inside the label, its written name and its color,
 * so nobody depends on the color alone. The meter is decoration for the name next to it.
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
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-md px-2 py-[3px] text-[13px] leading-[18px] font-medium tracking-normal whitespace-nowrap normal-case',
                tone.label,
            )}
        >
            <span aria-hidden="true" className="flex gap-0.5">
                {RISK_LEVELS.map((value, index) => (
                    <span
                        key={value}
                        className={cn(
                            'h-3 w-1 rounded-[2px]',
                            index < filled ? tone.fill : 'bg-border',
                        )}
                    />
                ))}
            </span>
            {label}
        </span>
    );
}
