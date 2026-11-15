import { sparklineOf } from '@/lib/engagement/sparkline';
import { cn } from '@/lib/utils';
import type { TrendPoint, TrendWave } from '@/types';

const WIDTH = 120;
const HEIGHT = 32;

/**
 * A theme across the waves in 120 × 32: every theme on the same months and the same scale of 0 to 100, the first
 * measurement as a dashed baseline and, when the theme was not asked in the latest wave, a dashed tail to it. A drawing
 * only: the cells beside it say the figures.
 */
export default function TrendSparkline({
    points,
    waves,
    falling,
}: {
    points: TrendPoint[];
    waves: TrendWave[];
    falling: boolean;
}) {
    const line = sparklineOf(points, waves, { width: WIDTH, height: HEIGHT });

    if (line === null) {
        return null;
    }

    return (
        <svg
            aria-hidden
            viewBox={`-3 -3 ${WIDTH + 6} ${HEIGHT + 6}`}
            className={cn(
                'h-8 w-[120px] overflow-visible',
                falling ? 'text-risk-alto' : 'text-primary',
            )}
        >
            <line
                x1={0}
                x2={WIDTH}
                y1={line.baseline}
                y2={line.baseline}
                className="stroke-muted-foreground"
                strokeDasharray="3 3"
                strokeWidth={1}
            />
            {line.tail !== null && (
                <path
                    d={line.tail}
                    fill="none"
                    className="stroke-muted-foreground"
                    strokeDasharray="3 3"
                />
            )}
            <path
                d={line.path}
                fill="none"
                stroke="currentColor"
                strokeWidth={2}
                strokeLinejoin="round"
            />
            <circle cx={line.dot.x} cy={line.dot.y} r={3} fill="currentColor" />
        </svg>
    );
}
