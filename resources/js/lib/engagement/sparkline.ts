import type { TrendPoint, TrendWave } from '@/types';

/** "4 mediciones · jun 2026 a sep 2026": how many waves the trend holds and which months they span. */
export function wavesSpan(waves: TrendWave[]): string | null {
    const first = waves[0];
    const last = waves.at(-1);

    if (first === undefined || last === undefined) {
        return null;
    }

    return waves.length === 1
        ? `1 medición · ${first.label}`
        : `${waves.length} mediciones · ${first.label} a ${last.label}`;
}

/** A small line of a theme across the waves: its path, the dot of the latest point and the first measurement's height. */
export type Sparkline = {
    path: string;
    /** From the latest point to the latest wave, dashed, when the theme was not asked in it. */
    tail: string | null;
    dot: { x: number; y: number };
    baseline: number;
};

const round1 = (value: number): number => Math.round(value * 10) / 10;

/**
 * Where a theme's points go in a plot of `width` × `height`: across by the wave they belong to, so every theme shares the
 * same months, and up on the common scale of 0 to 100.
 */
export function sparklineOf(
    points: TrendPoint[],
    waves: TrendWave[],
    { width, height }: { width: number; height: number },
): Sparkline | null {
    const step = waves.length > 1 ? width / (waves.length - 1) : 0;
    const placed = points.map((point) => ({
        x: round1(
            Math.max(
                0,
                waves.findIndex((wave) => wave.surveyId === point.surveyId),
            ) * step,
        ),
        y: round1(
            height - (Math.min(100, Math.max(0, point.value)) / 100) * height,
        ),
    }));
    const first = placed[0];
    const dot = placed.at(-1);

    if (first === undefined || dot === undefined) {
        return null;
    }

    return {
        path: placed
            .map(({ x, y }, index) => `${index === 0 ? 'M' : 'L'}${x} ${y}`)
            .join(''),
        tail:
            dot.x < width && waves.length > 1
                ? `M${dot.x} ${dot.y}L${width} ${dot.y}`
                : null,
        dot,
        baseline: first.y,
    };
}
