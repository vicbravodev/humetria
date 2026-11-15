/** A bar's length against the scale, 0 to 100 with one decimal: nothing for nothing or less, never past the full bar. */
export function percentOf(value: number, max: number): number {
    if (max <= 0 || value <= 0) {
        return 0;
    }

    return Math.min(100, Math.round((value / max) * 1000) / 10);
}

/** The top of a scale that reads well: `value` rounded up to the step, one step at least ("de $0 a $600"). */
export function niceCeiling(value: number, step: number): number {
    return Math.max(step, Math.ceil(value / step) * step);
}

/** Where a bar of a cascade starts and ends, as fractions of the plot from its bottom. */
export type CascadeBar = { from: number; to: number };

/**
 * The bars of a cascade: each value stacked on the ones before it, and the total from the axis. The plot takes the highest
 * and lowest points reached, so a value that lowers the cost goes down and the axis stays inside the plot.
 */
export function cascade(values: number[]): CascadeBar[] {
    const points = [0];

    for (const value of values) {
        points.push((points.at(-1) ?? 0) + value);
    }

    const low = Math.min(...points);
    const span = Math.max(...points) - low;
    const at = (point: number): number =>
        span === 0 ? 0 : (point - low) / span;
    const bar = (start: number, end: number): CascadeBar => ({
        from: at(Math.min(start, end)),
        to: at(Math.max(start, end)),
    });
    const total = points.at(-1) ?? 0;

    return [
        ...values.map((_, index) =>
            bar(points[index] ?? 0, points[index + 1] ?? 0),
        ),
        bar(0, total),
    ];
}

/** The bars grow one after another, 70 ms apart (design-system.md, Movimiento); written whole so Tailwind finds them. */
const DELAYS = [
    '[animation-delay:0ms]',
    '[animation-delay:70ms]',
    '[animation-delay:140ms]',
    '[animation-delay:210ms]',
    '[animation-delay:280ms]',
    '[animation-delay:350ms]',
    '[animation-delay:420ms]',
    '[animation-delay:490ms]',
    '[animation-delay:560ms]',
    '[animation-delay:630ms]',
    '[animation-delay:700ms]',
    '[animation-delay:770ms]',
];

/** The delay of the bar in `index`; the late ones wait as long as the last step. */
export function growDelay(index: number): string {
    return DELAYS[Math.min(index, DELAYS.length - 1)] ?? '';
}

/** "30 %": how much of the whole `value` is, whole, with a space before the sign (voice of the panel). */
export function percentLabel(value: number, max: number): string {
    return `${Math.round(percentOf(value, max))} %`;
}

/** "+16 pts", "−27 pts", "0 pts": how far `value` is from its reference, in whole points, with the typographic minus. */
export function pointsDelta(value: number, reference: number): string {
    const points = Math.round(value - reference);

    if (points === 0) {
        return '0 pts';
    }

    return `${points > 0 ? '+' : '−'}${Math.abs(points)} pts`;
}
