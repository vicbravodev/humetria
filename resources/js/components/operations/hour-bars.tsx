import { barsSummary } from '@/lib/operations-health';
import type { HourBar } from '@/lib/operations-health';
import { cn } from '@/lib/utils';

const TONES: Record<HourBar['tone'], string> = {
    idle: 'bg-track',
    ok: 'bg-chart-2',
    warning: 'bg-warning-foreground',
};

/** A service's last 24 hours, one bar each: the sentence says it to whoever does not see the colors. */
export default function HourBars({
    bars,
    limit,
}: {
    bars: HourBar[];
    limit: number;
}) {
    return (
        <div
            role="img"
            aria-label={barsSummary(bars, limit)}
            className="flex h-7 w-full gap-0.5 sm:w-60"
        >
            {bars.map((bar, index) => (
                <i
                    key={index}
                    title={bar.label}
                    className={cn(
                        'block flex-1 rounded-[2px]',
                        TONES[bar.tone],
                    )}
                />
            ))}
        </div>
    );
}
