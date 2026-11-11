import type { CSSProperties, ReactNode } from 'react';
import { icons } from '../components';
import { radius } from '../theme';
import { useTheme, type Palette } from './themes';

export const cardStyle = (c: Palette): CSSProperties => ({
    position: 'absolute',
    boxSizing: 'border-box',
    borderRadius: radius.card,
    background: c.card,
    border: `1px solid ${c.border}`,
});

export const Icon = ({ name, size = 28, color, stroke = 2, style }: { name: keyof typeof icons; size?: number; color?: string; stroke?: number; style?: CSSProperties }) => {
    const c = useTheme();
    return (
        <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke={color ?? c.fg} strokeWidth={stroke} strokeLinecap="round" strokeLinejoin="round" style={{ flexShrink: 0, ...style }}>
            {icons[name]}
        </svg>
    );
};

export const Card = ({ style, children }: { style?: CSSProperties; children?: ReactNode }) => {
    const c = useTheme();
    return <div style={{ ...cardStyle(c), ...style }}>{children}</div>;
};

export const Tag = ({ label, style }: { label: string; style?: CSSProperties }) => {
    const c = useTheme();
    return (
        <div style={{ display: 'inline-flex', alignItems: 'center', gap: 8, height: 42, padding: '0 14px', borderRadius: radius.control, background: c.accent, border: `1px solid ${c.border}`, fontSize: 26, fontWeight: 500, color: c.fg, whiteSpace: 'nowrap', ...style }}>
            <span style={{ width: 8, height: 8, borderRadius: 99, background: c.primary }} />
            {label}
        </div>
    );
};

export const CheckBadge = ({ size, style }: { size: number; style?: CSSProperties }) => {
    const c = useTheme();
    return (
        <div style={{ width: size, height: size, borderRadius: 999, background: c.primary, display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0, ...style }}>
            <Icon name="check" size={size * 0.56} color={c.primaryFg} stroke={2.6} />
        </div>
    );
};

export const Button = ({ label, pressed = 0, style }: { label: string; pressed?: number; style?: CSSProperties }) => {
    const c = useTheme();
    return (
        <div style={{ height: 64, padding: '0 28px', display: 'inline-flex', alignItems: 'center', gap: 12, borderRadius: radius.control, background: c.primary, color: c.primaryFg, fontSize: 28, fontWeight: 600, transform: `scale(${1 - 0.04 * pressed})`, whiteSpace: 'nowrap', ...style }}>
            {label}
        </div>
    );
};

/** Medidor de arco (forma de la «h» del logo): `value` de 0 a 100. */
export const Meter = ({ value, size, children }: { value: number; size: number; children?: ReactNode }) => {
    const c = useTheme();
    const d = 'M10 49V33A22 22 0 0 1 54 33V49';
    return (
        <div style={{ position: 'relative', width: size, height: size }}>
            <svg width={size} height={size} viewBox="0 0 64 64" fill="none" strokeWidth={7} strokeLinecap="round">
                <path d={d} stroke={c.track} pathLength={100} />
                <path d={d} stroke={c.primary} pathLength={100} strokeDasharray={100} strokeDashoffset={100 - value} />
            </svg>
            <div style={{ position: 'absolute', inset: 0, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', paddingTop: size * 0.1 }}>{children}</div>
        </div>
    );
};

export const PHONE = { w: 360, h: 700 } as const;

/** Barra de estado: hora a la izquierda; señal, wifi y batería a la derecha. */
const StatusBar = ({ time }: { time: string }) => {
    const c = useTheme();
    return (
        <>
            <div style={{ position: 'absolute', left: 30, top: 17, fontSize: 19, fontWeight: 600, letterSpacing: 0.2, color: c.fg }}>{time}</div>
            <svg width={74} height={16} viewBox="0 0 74 16" style={{ position: 'absolute', right: 26, top: 19 }} fill={c.fg}>
                <rect x="0" y="10" width="3.4" height="5" rx="1" />
                <rect x="5" y="7" width="3.4" height="8" rx="1" />
                <rect x="10" y="4" width="3.4" height="11" rx="1" />
                <rect x="15" y="1" width="3.4" height="14" rx="1" />
                <path d="M30 6.2a9 9 0 0 1 12 0M32.4 9a5.6 5.6 0 0 1 7.2 0" stroke={c.fg} strokeWidth="1.8" strokeLinecap="round" fill="none" />
                <circle cx="36" cy="12.4" r="1.6" />
                <rect x="49.5" y="2" width="20" height="11.5" rx="3.4" fill="none" stroke={c.fg} strokeWidth="1.2" opacity="0.5" />
                <rect x="51.3" y="3.8" width="14.6" height="7.9" rx="2" />
                <rect x="71" y="5.6" width="1.8" height="4.3" rx="0.9" opacity="0.5" />
            </svg>
        </>
    );
};

/**
 * Teléfono genérico de pantalla completa: bisel negro, isla con cámara, barra de estado, botones laterales e indicador de
 * inicio. Sin marcas. La pantalla toma el tema; el cuerpo es negro en ambos. Contenido útil desde y≈66.
 */
export const Phone = ({ style, time = '10:24', children }: { style?: CSSProperties; time?: string; children?: ReactNode }) => {
    const c = useTheme();
    const side = (top: number, h: number, left: boolean): CSSProperties => ({ position: 'absolute', [left ? 'left' : 'right']: -4, top, width: 4, height: h, borderRadius: 2, background: '#242c2a' });
    return (
        <div style={{ position: 'absolute', width: PHONE.w, height: PHONE.h, borderRadius: 60, background: '#070a09', border: '2px solid #2b3532', boxSizing: 'border-box', padding: 9, boxShadow: '0 28px 60px rgba(0,0,0,.30), inset 0 0 0 1px rgba(255,255,255,.06)', ...style }}>
            <div data-probe-ignore style={side(130, 34, true)} />
            <div data-probe-ignore style={side(190, 62, true)} />
            <div data-probe-ignore style={side(266, 62, true)} />
            <div data-probe-ignore style={side(212, 96, false)} />
            <div style={{ position: 'relative', width: '100%', height: '100%', borderRadius: 50, background: c.bg, overflow: 'hidden' }}>
                {children}
                <StatusBar time={time} />
                <div style={{ position: 'absolute', top: 12, left: '50%', width: 108, height: 32, marginLeft: -54, borderRadius: 99, background: '#000' }}>
                    <div style={{ position: 'absolute', right: 13, top: 10, width: 12, height: 12, borderRadius: 99, background: 'radial-gradient(circle at 35% 35%, #1d2b3a, #05080b 70%)' }} />
                </div>
                <div style={{ position: 'absolute', bottom: 9, left: '50%', width: 124, height: 5, marginLeft: -62, borderRadius: 99, background: c.fg, opacity: 0.85 }} />
                <div data-probe-ignore style={{ position: 'absolute', inset: 0, background: 'linear-gradient(115deg, rgba(255,255,255,.07) 0%, rgba(255,255,255,0) 32%)', pointerEvents: 'none' }} />
            </div>
        </div>
    );
};
