import { Easing, interpolate } from 'remotion';
import type { CSSProperties } from 'react';

export const easeOut = Easing.bezier(0.23, 1, 0.32, 1);
export const easeInOut = Easing.bezier(0.77, 0, 0.175, 1);

/** Avance 0→1 entre `start` y `start + dur`, con curva. */
export const tween = (
    frame: number,
    start: number,
    dur: number,
    ease: (t: number) => number = easeOut,
): number =>
    interpolate(frame, [start, start + dur], [0, 1], {
        easing: ease,
        extrapolateLeft: 'clamp',
        extrapolateRight: 'clamp',
    });

export const lerp = (a: number, b: number, t: number): number => a + (b - a) * t;

/** Entrada estándar: opacidad, escala 0.94→1, desplazamiento y desenfoque 4px→0. */
export const enter = (
    frame: number,
    start: number,
    opts: { dur?: number; y?: number; x?: number; scale?: number; blur?: number } = {},
): CSSProperties => {
    const t = tween(frame, start, opts.dur ?? 18);
    return fx(t, opts);
};

const fx = (
    t: number,
    opts: { y?: number; x?: number; scale?: number; blur?: number },
): CSSProperties => {
    const s = lerp(opts.scale ?? 0.94, 1, t);
    const x = lerp(opts.x ?? 0, 0, t);
    const y = lerp(opts.y ?? 12, 0, t);
    const blur = lerp(opts.blur ?? 4, 0, t);
    return {
        opacity: t,
        transform: `translate(${x}px, ${y}px) scale(${s})`,
        filter: blur > 0.01 ? `blur(${blur}px)` : 'none',
    };
};
