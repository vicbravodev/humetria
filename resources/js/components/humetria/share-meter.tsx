import { shareOf } from '@/lib/results';
import { cn } from '@/lib/utils';

/**
 * How large a part of a group is (design system: ShareMeter): the fill grows with the share, in the tone of a high
 * risk over a track of the same tone, so the whole bar reads as one scale. Always next to the written count.
 */
export default function ShareMeter({
    value,
    max,
    label,
    className,
}: {
    value: number;
    max: number;
    label: string;
    className?: string;
}) {
    return (
        <div
            role="meter"
            aria-label={label}
            aria-valuemin={0}
            aria-valuemax={max}
            aria-valuenow={value}
            className={cn(
                'h-2 w-full overflow-hidden rounded-full bg-risk-alto-bg',
                className,
            )}
        >
            <div
                className="h-full rounded-full bg-risk-alto [-webkit-print-color-adjust:exact] [print-color-adjust:exact]"
                style={{ width: `${shareOf(value, max)}%` }}
            />
        </div>
    );
}
