import { interpolate } from 'remotion';
import { easeInOut, lerp, tween } from '../ease';
import { c } from '../theme';
import { HC } from './timeline';

type Key = readonly [number, number, number];

// Hacia dónde mira: [frame, dx, dy] en unidades del SVG (ojo r=40, pupila r=11).
const LOOK: readonly Key[] = [
    [0, 0, 0],
    [HC.q1Type - 10, 0, 0],
    [HC.q1Type + 6, 3, 13],
    [HC.q1Send, 3, 13],
    [HC.q1Send + 14, -13, -9],
    [HC.q1Send + 40, 13, -9],
    [HC.q1Send + 62, -5, -13],
    [HC.ans1 + 4, 0, 0],
    [HC.q2Type - 10, 0, 0],
    [HC.q2Type + 6, 3, 13],
    [HC.q2Send, 3, 13],
    [HC.q2Send + 14, -13, -9],
    [HC.q2Send + 34, 13, -9],
    [HC.ans2 + 4, 0, 0],
    [HC.end, 0, 0],
];

const BLINKS = [62, 214, 300, 470, 596, 712] as const;
const THINK: readonly (readonly [number, number])[] = [
    [HC.q1Send + 6, HC.ans1],
    [HC.q2Send + 6, HC.ans2],
];
const RAYS_BASE = [1, 1, 1, 0.25, 0.25] as const;

export const humiState = (f: number) => {
    const frames = LOOK.map((k) => k[0]);
    const opts = { easing: easeInOut, extrapolateLeft: 'clamp', extrapolateRight: 'clamp' } as const;
    const dx = interpolate(f, frames, LOOK.map((k) => k[1]), opts);
    const dy = interpolate(f, frames, LOOK.map((k) => k[2]), opts);
    const blink = Math.max(
        0,
        ...BLINKS.map((b) => tween(f, b, 3, easeInOut) * (1 - tween(f, b + 3, 4, easeInOut))),
    );
    const think = Math.max(0, ...THINK.map(([a, z]) => tween(f, a, 8, easeInOut) * (1 - tween(f, z - 8, 10, easeInOut))));
    const rays = RAYS_BASE.map((base, i) => {
        const wave = 0.5 + 0.5 * Math.sin((2 * Math.PI * f) / 22 - i * 1.15);
        return lerp(base, 0.2 + 0.8 * wave, think);
    });
    // Respira: periodo de 125 frames, divide exacto la duración del loop.
    const bob = Math.sin((2 * Math.PI * f) / 125) * 4;
    return { dx, dy, blink, rays, bob };
};

const RAYS = [
    [63, 114, 49, 106],
    [104, 73, 96, 59],
    [160, 58, 160, 42],
    [216, 73, 224, 59],
    [257, 114, 271, 106],
] as const;

export const HumiLive = ({ f, width }: { f: number; width: number }) => {
    const s = humiState(f);
    const eyeY = 1 - 0.9 * s.blink;
    return (
        <svg width={width} height={(width * 340) / 320} viewBox="0 0 320 340" style={{ overflow: 'visible' }}>
            <g transform={`translate(0 ${s.bob})`}>
                <g stroke={c.primary} strokeWidth="8" strokeLinecap="round">
                    {RAYS.map(([x1, y1, x2, y2], i) => (
                        <line key={x1} x1={x1} y1={y1} x2={x2} y2={y2} opacity={s.rays[i]} />
                    ))}
                </g>
                <path
                    d="M60 276 V170 A100 100 0 0 1 260 170 V276 A24 24 0 0 1 236 300 H84 A24 24 0 0 1 60 276 Z"
                    fill={c.primary}
                />
                <g transform={`translate(0 170) scale(1 ${eyeY}) translate(0 -170)`}>
                    <circle cx="134" cy="170" r="40" fill={c.fg} />
                    <circle cx="186" cy="170" r="40" fill={c.fg} />
                    <g transform={`translate(${s.dx} ${s.dy})`}>
                        <circle cx="134" cy="174" r="11" fill={c.bg} />
                        <circle cx="186" cy="174" r="11" fill={c.bg} />
                        <circle cx="138" cy="170" r="3.5" fill={c.fg} />
                        <circle cx="190" cy="170" r="3.5" fill={c.fg} />
                    </g>
                </g>
                <path d="M140 234 Q160 252 180 234" fill="none" stroke={c.fg} strokeWidth="7" strokeLinecap="round" />
            </g>
            <rect x="92" y="294" width="52" height="22" rx="11" fill={c.primary} />
            <rect x="176" y="294" width="52" height="22" rx="11" fill={c.primary} />
        </svg>
    );
};
