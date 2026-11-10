import type { TrendDirection, TrendPoint } from '@/types';

/** One column of a series' chart: the shape the column chart takes. */
export type TrendColumn = {
    key: string;
    label: string;
    valueText: string;
    tooltip: string;
    /** Its height against the tallest, 0 to 100. */
    percent: number;
};

/** "Subió", "Bajó" or "Igual"; nothing when the waves were too small for an arrow. */
export function arrowLabel(direction: TrendDirection): string {
    switch (direction) {
        case 'up':
            return 'Subió';
        case 'down':
            return 'Bajó';
        case 'same':
            return 'Igual';
        default:
            return '';
    }
}

/**
 * The columns of a series, oldest first. A share (0 to 100) is scaled so the tallest takes the whole height; the
 * recommendation index (-100 to 100) puts zero in the middle.
 */
export function columnsOf(
    points: TrendPoint[],
    kind: 'percent' | 'index',
): TrendColumn[] {
    const tallest = Math.max(1, ...points.map((point) => point.value));

    return points.map((point) => {
        const valueText =
            kind === 'index'
                ? `${point.value > 0 ? '+' : ''}${point.value}`
                : `${point.value} %`;
        const arrow = arrowLabel(point.direction);

        return {
            key: point.surveyId,
            label: point.label,
            valueText,
            tooltip: `${point.label}: ${valueText}${arrow === '' ? '' : ` · ${arrow}`}`,
            percent:
                kind === 'index'
                    ? (point.value + 100) / 2
                    : Math.round((Math.max(0, point.value) / tallest) * 100),
        };
    });
}
