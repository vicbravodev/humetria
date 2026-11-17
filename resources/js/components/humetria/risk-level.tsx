import { levelSegments, RISK_LEVELS } from '@/lib/results';
import { riskFill, riskLabel } from '@/lib/risk-tones';
import { cn } from '@/lib/utils';
import type { RiskLevelValue } from '@/types';

/**
 * A risk level (design system: RiskLevel): a meter of five segments inside the label, its written name and its color,
 * so nobody depends on the color alone. The meter is decoration for the name next to it. `lg` is the result a person reads
 * about themselves (finding 34): the meter beside the label, both big, as the one thing on the card.
 */
export default function RiskLevel({
    level,
    label,
    size = 'sm',
}: {
    level: RiskLevelValue;
    label: string;
    size?: 'sm' | 'lg';
}) {
    const filled = levelSegments(level);
    const large = size === 'lg';

    return (
        <span
            className={cn(
                'inline-flex items-center whitespace-nowrap normal-case',
                large
                    ? 'gap-3.5'
                    : 'gap-1.5 rounded-md px-2 py-[3px] text-caption font-medium tracking-normal',
                !large && riskLabel(level),
            )}
        >
            <span
                aria-hidden="true"
                className={cn('flex', large ? 'gap-1' : 'gap-0.5')}
            >
                {RISK_LEVELS.map((value, index) => (
                    <span
                        key={value}
                        className={cn(
                            large
                                ? 'h-[34px] w-3.5 rounded-[5px]'
                                : 'h-3 w-1 rounded-[2px]',
                            index < filled ? riskFill(level) : 'bg-border',
                        )}
                    />
                ))}
            </span>
            {large ? (
                <span
                    className={cn(
                        'inline-flex min-h-9 items-center rounded-full px-3.5 text-lg font-semibold',
                        riskLabel(level),
                    )}
                >
                    {label}
                </span>
            ) : (
                label
            )}
        </span>
    );
}
