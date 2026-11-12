import type { TrendPoint } from '@/types';

/** A measurement of a theme: its share and its month, or a dash when there is none. */
export default function TrendMeasurement({
    point,
}: {
    point: TrendPoint | undefined;
}) {
    if (point === undefined) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <span className="flex flex-col md:items-end">
            <span className="font-medium tabular-nums">{point.value} %</span>
            <span className="text-caption text-muted-foreground">
                {point.label}
            </span>
        </span>
    );
}
