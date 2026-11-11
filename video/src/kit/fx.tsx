import type { CSSProperties, ReactNode } from 'react';
import { Easing, interpolate, useCurrentFrame } from 'remotion';
import { easeInOut, easeOut, lerp, tween } from '../ease';
import { useTheme } from './themes';
import { dur } from './tokens';

/** Rebote corto (≤ 4 %) para insignias y chips que «caen» en su lugar. */
export const easeBack = Easing.bezier(0.34, 1.56, 0.64, 1);

export type Pt = { x: number; y: number };

/** Fondo con profundidad: dos resplandores del acento que derivan despacio. Va bajo todas las escenas, en tiempo global. */
export const Backdrop = () => {
    const f = useCurrentFrame();
    const c = useTheme();
    const glow = (pct: number): string => `color-mix(in srgb, ${c.primary} ${pct}%, transparent)`;
    const a = Math.sin(f / 170);
    const b = Math.cos(f / 210);
    return (
        <div
            style={{
                position: 'absolute',
                inset: 0,
                background: [
                    `radial-gradient(900px 620px at ${22 + 6 * a}% ${18 + 4 * b}%, ${glow(11)}, transparent 70%)`,
                    `radial-gradient(1000px 700px at ${82 - 6 * b}% ${88 - 5 * a}%, ${glow(8)}, transparent 70%)`,
                ].join(','),
            }}
        />
    );
};

/** Cámara: empuje lento hacia el contenido durante la escena. Margen de seguridad: nada llega a los bordes. */
export const PushIn = ({ f, total, still, children }: { f: number; total: number; still?: boolean; children: ReactNode }) => {
    const t = still === true ? 0 : tween(f, 0, total, (x) => x);
    return <div style={{ position: 'absolute', inset: 0, transform: `scale(${1 + 0.022 * t}) translateY(${-8 * t}px)`, transformOrigin: '50% 45%' }}>{children}</div>;
};

/** Titular: cada palabra sube desde una máscara; la última lleva el acento. */
export const Headline = ({ f, at, text }: { f: number; at: number; text: string }) => {
    const c = useTheme();
    const words = text.split(' ');
    return (
        <div data-probe-ignore style={{ position: 'absolute', left: 110, top: 134, display: 'flex', gap: 18, fontSize: 64, fontWeight: 600, letterSpacing: -1.6, lineHeight: 1.1 }}>
            {words.map((w, i) => {
                const t = tween(f, at + i * 4, 22);
                return (
                    <span key={`${w}-${i}`} data-probe-ignore style={{ display: 'inline-block', overflow: 'hidden', paddingBottom: 10, marginBottom: -10 }}>
                        <span style={{ display: 'inline-block', transform: `translateY(${(1 - t) * 105}%)`, opacity: Math.min(1, t * 2), color: i === words.length - 1 ? c.primary : c.fg }}>{w}</span>
                    </span>
                );
            })}
        </div>
    );
};

/** Punto sobre una curva de `from` a `to` (el arco se levanta `lift` px). */
export const arc = (from: Pt, to: Pt, t: number, lift: number): Pt => {
    const cx = (from.x + to.x) / 2;
    const cy = (from.y + to.y) / 2 - lift;
    const u = 1 - t;
    return { x: u * u * from.x + 2 * u * t * cx + t * t * to.x, y: u * u * from.y + 2 * u * t * cy + t * t * to.y };
};

/** Un punto que viaja; desaparece al llegar (lo que importa es lo que llena). */
export const FlyDot = ({ f, start, len = 24, from, to, lift = 60, size = 16, color }: { f: number; start: number; len?: number; from: Pt; to: Pt; lift?: number; size?: number; color?: string }) => {
    const c = useTheme();
    if (f < start || f > start + len + 2) {
        return null;
    }
    const t = tween(f, start, len, easeInOut);
    const p = arc(from, to, t, lift);
    const fade = tween(t, 0, 0.12) * (1 - tween(t, 0.9, 0.1));
    return <div style={{ position: 'absolute', left: p.x - size / 2, top: p.y - size / 2, width: size, height: size, borderRadius: 99, background: color ?? c.primary, opacity: fade, transform: `scale(${lerp(0.7, 1, t)})` }} />;
};

/** Cursor que sigue una trayectoria y hace clic con una onda. */
export const Cursor = ({ f, path, clicks }: { f: number; path: readonly { at: number; x: number; y: number }[]; clicks: readonly number[] }) => {
    const c = useTheme();
    const first = path[0];
    const last = path[path.length - 1];
    if (!first || !last) {
        return null;
    }
    const ats = path.map((p) => p.at);
    const x = interpolate(f, ats, path.map((p) => p.x), { easing: easeInOut, extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
    const y = interpolate(f, ats, path.map((p) => p.y), { easing: easeInOut, extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
    const vis = tween(f, first.at - 4, 8);
    const down = clicks.reduce((m, k) => Math.max(m, 1 - Math.min(1, Math.abs(f - k - 3) / 4)), 0);
    return (
        <>
            {clicks.map((k) => {
                const t = tween(f, k, 16, easeOut);
                return t > 0 && t < 1 ? <div key={k} style={{ position: 'absolute', left: x - 30, top: y - 30, width: 60, height: 60, borderRadius: 99, border: `3px solid ${c.primary}`, opacity: 1 - t, transform: `scale(${lerp(0.3, 1.5, t)})` }} /> : null;
            })}
            <svg width={44} height={44} viewBox="0 0 24 24" style={{ position: 'absolute', left: x - 6, top: y - 4, opacity: vis, transform: `scale(${1 - 0.1 * down})`, filter: 'drop-shadow(0 4px 8px rgba(0,0,0,.35))' }}>
                <path d="M5 3l14 7-6 2-2 6z" fill={c.fg} stroke={c.bg} strokeWidth={1.4} strokeLinejoin="round" />
            </svg>
        </>
    );
};

/** Aviso flotante con avance (como el «Enviando recordatorios» del producto). */
export const Toast = ({ style, children }: { style?: CSSProperties; children: ReactNode }) => {
    const c = useTheme();
    return <div style={{ position: 'absolute', boxSizing: 'border-box', borderRadius: 14, background: c.card, border: `1px solid ${c.border}`, boxShadow: '0 18px 40px rgba(0,0,0,.28)', padding: '20px 28px', ...style }}>{children}</div>;
};

/** Entrada con rebote para chips e insignias. */
export const pop = (f: number, at: number): CSSProperties => {
    const t = tween(f, at, dur.enter + 4, easeBack);
    return { opacity: Math.min(1, tween(f, at, 8)), transform: `scale(${lerp(0.6, 1, t)})` };
};
