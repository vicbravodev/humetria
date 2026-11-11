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

export const Phone = ({ style, children }: { style?: CSSProperties; children?: ReactNode }) => {
    const c = useTheme();
    return (
        <div style={{ position: 'absolute', width: PHONE.w, height: PHONE.h, borderRadius: 54, background: c.card, border: `1px solid ${c.border}`, boxSizing: 'border-box', padding: 10, ...style }}>
            <div style={{ position: 'relative', width: '100%', height: '100%', borderRadius: 44, background: c.bg, overflow: 'hidden' }}>
                <div style={{ position: 'absolute', top: 14, left: '50%', width: 96, height: 28, marginLeft: -48, borderRadius: 99, background: c.card }} />
                {children}
            </div>
        </div>
    );
};
