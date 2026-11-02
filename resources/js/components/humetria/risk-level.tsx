import { levelSegments, RISK_LEVELS } from '@/lib/results';
import { riskFill, riskLabel } from '@/lib/risk-tones';
import { cn } from '@/lib/utils';
import type { RiskLevelValue } from '@/types';

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

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-md px-2 py-[3px] text-[13px] leading-[18px] font-medium tracking-normal whitespace-nowrap normal-case',
                riskLabel(level),
            )}
        >
            <span aria-hidden="true" className="flex gap-0.5">
                {RISK_LEVELS.map((value, index) => (
                    <span
                        key={value}
                        className={cn(
                            'h-3 w-1 rounded-[2px]',
                            index < filled ? riskFill(level) : 'bg-border',
                        )}
                    />
                ))}
            </span>
            {label}
        </span>
    );
}
