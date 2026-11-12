import type { TrendPoint, TrendSeries } from '@/types';

/** One column of a series' chart: the shape the column chart takes. */
export type TrendColumn = {
    key: string;
    label: string;
    valueText: string;
    tooltip: string;
    /** Its height on the scale of 0 to 100. */
    percent: number;
};

/** Which way a change goes once the noise is taken out: two points or less either way is no change. */
export type TrendBand = 'up' | 'down' | 'same';

/** How far the latest wave is from another one, and from which. */
export type TrendChange = { points: number; band: TrendBand; from: string };

/** The band of no change: a move of this many points or less is noise, not news. */
const NO_CHANGE = 2;

export function bandOf(points: number): TrendBand {
    if (points > NO_CHANGE) {
        return 'up';
    }

    return points < -NO_CHANGE ? 'down' : 'same';
}

function change(from: TrendPoint, to: TrendPoint): TrendChange {
    const points = to.value - from.value;

    return { points, band: bandOf(points), from: from.label };
}

/**
 * The latest wave against the first measurement. Nothing with one wave, nor when the first or the latest were too small
 * for an arrow (the server leaves the arrow out under the minimum of a trend).
 */
export function changeSinceFirst(points: TrendPoint[]): TrendChange | null {
    const first = points[0];
    const latest = points.at(-1);

    if (
        points.length < 2 ||
        first === undefined ||
        latest === undefined ||
        points[1]?.direction === null ||
        latest.direction === null
    ) {
        return null;
    }

    return change(first, latest);
}

/** The latest wave against the one before it; nothing when there is no arrow between them. */
export function changeSincePrevious(points: TrendPoint[]): TrendChange | null {
    const previous = points.at(-2);
    const latest = points.at(-1);

    return previous === undefined ||
        latest === undefined ||
        latest.direction === null
        ? null
        : change(previous, latest);
}

/** "Subió 12 pts", "Bajó 5 pts" or "Sin cambio". */
export function changeLabel(of: TrendChange): string {
    const points = Math.abs(of.points);
    const size = `${points} ${points === 1 ? 'pt' : 'pts'}`;

    switch (of.band) {
        case 'up':
            return `Subió ${size}`;
        case 'down':
            return `Bajó ${size}`;
        case 'same':
            return 'Sin cambio';
    }
}

/** "−1 pt contra ago 2026 (sin cambio).": the latest wave against the one before; nothing without an arrow. */
export function versusPreviousText(of: TrendChange | null): string {
    if (of === null) {
        return '';
    }

    const size = Math.abs(of.points);
    const same = of.band === 'same' ? ' (sin cambio)' : '';

    return `${signedText(of.points)} ${size === 1 ? 'pt' : 'pts'} contra ${of.from}${same}.`;
}

/** The first measurement as the dashed baseline of a chart, once there is a second one to read against it. */
export function baselineOf(
    points: TrendPoint[],
): { percent: number; label: string } | undefined {
    const first = points[0];

    return first === undefined || points.length < 2
        ? undefined
        : {
              percent: first.value,
              label: `Línea base: ${first.label}, ${first.value} %`,
          };
}

/** "−27", "0", "+13": an index with its sign, with a real minus. */
export function signedText(value: number): string {
    if (value === 0) {
        return '0';
    }

    return `${value > 0 ? '+' : '−'}${Math.abs(value)}`;
}

function valueText(value: number, kind: TrendSeries['kind']): string {
    return kind === 'index' ? signedText(value) : `${value} %`;
}

/** The columns of a series, oldest first, every share from zero on the scale of 0 to 100. */
export function columnsOf(
    points: TrendPoint[],
    kind: TrendSeries['kind'],
): TrendColumn[] {
    return points.map((point, index) => {
        const text = valueText(point.value, kind);
        const previous = points[index - 1];
        const moved =
            previous === undefined || point.direction === null
                ? ''
                : ` · ${changeLabel(change(previous, point))}`;

        return {
            key: point.surveyId,
            label: point.label,
            valueText: text,
            tooltip: `${point.label}: ${text}${moved}`,
            percent: Math.min(100, Math.max(0, point.value)),
        };
    });
}

/** A column of a chart around zero: where it starts from the top and how tall it is, in percent of the plot. */
export type SignedColumn = TrendColumn & { top: number; height: number };

const round1 = (value: number): number => Math.round(value * 10) / 10;

/**
 * The recommendation index (−100 to 100) as columns that rise from zero or hang from it, on an axis rounded to fifty
 * that always holds zero. `zero` is where the axis line goes, in percent from the top.
 */
export function signedColumnsOf(points: TrendPoint[]): {
    min: number;
    max: number;
    zero: number;
    columns: SignedColumn[];
} {
    const values = points.map((point) => point.value);
    const min = Math.min(0, Math.floor(Math.min(0, ...values) / 50) * 50);
    const top = Math.max(0, Math.ceil(Math.max(0, ...values) / 50) * 50);
    const max = top === min ? min + 50 : top;
    const span = max - min;

    return {
        min,
        max,
        zero: round1((max / span) * 100),
        columns: columnsOf(points, 'index').map((column, index) => {
            const value = values[index] ?? 0;

            return {
                ...column,
                top: round1(((max - Math.max(0, value)) / span) * 100),
                height: round1((Math.abs(value) / span) * 100),
            };
        }),
    };
}

/** The themes by how much they moved since their first measurement, what rose most first; what cannot compare, last. */
export function sortByChange(series: TrendSeries[]): TrendSeries[] {
    const pointsOf = (one: TrendSeries): number =>
        changeSinceFirst(one.points)?.points ?? Number.NEGATIVE_INFINITY;

    return [...series].sort((a, b) => pointsOf(b) - pointsOf(a));
}

function engagementSentence(first: TrendChange, previous: TrendChange | null) {
    const size = Math.abs(first.points);
    const amount = `${size} ${size === 1 ? 'punto' : 'puntos'}`;
    const holds = previous?.band === 'same' ? ' y se mantiene' : '';

    switch (first.band) {
        case 'up':
            return `El compromiso subió ${amount} desde ${first.from}${holds}.`;
        case 'down':
            return `El compromiso bajó ${amount} desde ${first.from}${holds}.`;
        case 'same':
            return `El compromiso se mantiene desde ${first.from}.`;
    }
}

/**
 * The sentence over the trend: how the engagement moved since its first measurement and, when it moved, where the
 * recommendation went. Nothing to read with a single wave.
 */
export function trendReading(
    index: TrendPoint[],
    recommendation: TrendPoint[],
): string | null {
    const first = changeSinceFirst(index);

    if (first === null) {
        return null;
    }

    const sentence = engagementSentence(first, changeSincePrevious(index));
    const moved = changeSinceFirst(recommendation);
    const from = recommendation[0];
    const to = recommendation.at(-1);

    if (moved === null || moved.band === 'same' || !from || !to) {
        return sentence;
    }

    const verb = moved.band === 'up' ? 'mejora' : 'baja';

    return `${sentence} La recomendación ${verb}: de ${signedText(from.value)} a ${signedText(to.value)}.`;
}
