import { cn } from '@/lib/utils';

/**
 * Progress against a target (design system: Progress). The fill is primary until the target is met, then chart-3, to
 * show it closed without a traffic-light green. Always next to a written count: the bar alone is not enough.
 */
export default function ProgressBar({
    value,
    max,
    label,
}: {
    value: number;
    max: number;
    label: string;
}) {
    const complete = max > 0 && value >= max;
    const percent = max === 0 ? 0 : Math.min(100, (value / max) * 100);

    return (
        <div
            role="progressbar"
            aria-label={label}
            aria-valuemin={0}
            aria-valuemax={max}
            aria-valuenow={Math.min(value, max)}
            className="h-2 w-full overflow-hidden rounded-full bg-muted"
        >
            <div
                className={cn(
                    'h-full rounded-full transition-[width] duration-300',
                    complete ? 'bg-chart-3' : 'bg-primary',
                )}
                style={{ width: `${percent}%` }}
            />
        </div>
    );
}
