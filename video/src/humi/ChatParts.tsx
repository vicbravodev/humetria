import type { CSSProperties, ReactNode } from 'react';
import { CheckBadge, Icon } from '../components';
import { easeInOut, enter, lerp, tween } from '../ease';
import { c, radius } from '../theme';
import { WORD_GAP } from './timeline';

/** Bloque del chat: su alto crece al aparecer y empuja hacia arriba lo anterior (chat anclado abajo). */
export const Block = ({ f, at, h, children }: { f: number; at: number; h: number; children: ReactNode }) => {
    if (f < at) {
        return null;
    }
    return (
        <div style={{ position: 'relative', flexShrink: 0, height: h * tween(f, at, 16, easeInOut) }}>
            <div style={{ position: 'absolute', left: 0, right: 0, top: 0 }}>{children}</div>
        </div>
    );
};

export const UserBubble = ({ f, at, text }: { f: number; at: number; text: string }) => (
    <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
        <div
            style={{
                maxWidth: 760,
                padding: '20px 26px',
                borderRadius: '14px 14px 4px 14px',
                background: c.accent,
                border: `1px solid ${c.border}`,
                fontSize: 38,
                lineHeight: 1.3,
                color: c.fg,
                transformOrigin: 'bottom right',
                ...enter(f, at, { dur: 18, y: 20, scale: 0.96 }),
            }}
        >
            {text}
        </div>
    </div>
);

export const ToolStep = ({ f, at, done, label }: { f: number; at: number; done: number; label: string }) => {
    const d = tween(f, done, 8, easeInOut);
    return (
        <div
            style={{
                display: 'flex',
                alignItems: 'center',
                gap: 16,
                height: 52,
                fontSize: 34,
                color: c.mutedFg,
                ...enter(f, at, { dur: 16, y: 10, scale: 1 }),
            }}
        >
            <div style={{ position: 'relative', width: 36, height: 36, flexShrink: 0 }}>
                <svg
                    width={36}
                    height={36}
                    viewBox="0 0 36 36"
                    style={{ position: 'absolute', inset: 0, opacity: 1 - d, transform: `rotate(${f * 14}deg)` }}
                >
                    <circle cx="18" cy="18" r="14" fill="none" stroke={c.track} strokeWidth="4" />
                    <circle
                        cx="18"
                        cy="18"
                        r="14"
                        fill="none"
                        stroke={c.primary}
                        strokeWidth="4"
                        strokeLinecap="round"
                        strokeDasharray="22 66"
                    />
                </svg>
                <CheckBadge
                    size={36}
                    style={{ position: 'absolute', inset: 0, opacity: d, transform: `scale(${lerp(0.94, 1, d)})` }}
                />
            </div>
            {label}
        </div>
    );
};

/** Respuesta que se escribe palabra por palabra. Todas las palabras ocupan su lugar desde el inicio: no hay reflujo. */
export const Streamed = ({ f, at, text }: { f: number; at: number; text: string }) => (
    <div style={{ fontSize: 40, lineHeight: 1.35, color: c.fg }}>
        {text.split(' ').map((w, i) => {
            const t = tween(f, at + i * WORD_GAP, 8);
            return (
                <span key={`${w}-${i}`}>
                    <span
                        style={{
                            display: 'inline-block',
                            opacity: t,
                            filter: t < 1 ? `blur(${4 * (1 - t)}px)` : 'none',
                            transform: `translateY(${6 * (1 - t)}px)`,
                        }}
                    >
                        {w}
                    </span>{' '}
                </span>
            );
        })}
    </div>
);

export const SourceChip = ({ label, style }: { label: string; style?: CSSProperties }) => (
    <div
        style={{
            display: 'inline-flex',
            alignItems: 'center',
            gap: 10,
            height: 56,
            padding: '0 18px 0 14px',
            borderRadius: radius.control,
            background: c.muted,
            border: `1px solid ${c.border}`,
            fontSize: 34,
            color: c.fg,
            whiteSpace: 'nowrap',
            ...style,
        }}
    >
        <Icon name="file" size={28} color={c.primary} />
        {label}
    </div>
);

export const PrimaryButton = ({ f, at, press, label }: { f: number; at: number; press: number; label: string }) => {
    const p = tween(f, press, 4, easeInOut) * (1 - tween(f, press + 5, 10, easeInOut));
    const e = enter(f, at, { dur: 16, y: 10 });
    return (
        <div style={{ display: 'flex', ...e }}>
            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: 12,
                    height: 68,
                    padding: '0 26px',
                    borderRadius: radius.control,
                    background: c.primary,
                    color: c.primaryFg,
                    fontSize: 34,
                    fontWeight: 600,
                    transform: `scale(${lerp(1, 0.97, p)})`,
                }}
            >
                {label}
                <Icon name="arrowRight" size={30} color={c.primaryFg} stroke={2.4} />
            </div>
        </div>
    );
};

const SCENARIOS = [
    { label: 'Empresa · 4 %', value: '$4.1 M', v: 4.1, on: false },
    { label: 'Punto medio · 5.5 %', value: '$5.6 M', v: 5.6, on: true },
    { label: 'Sindicato · 7 %', value: '$7.2 M', v: 7.2, on: false },
] as const;

export const ScenarioCard = ({ f, at }: { f: number; at: number }) => (
    <div
        style={{
            padding: '22px 28px 26px',
            borderRadius: radius.card,
            background: c.muted,
            border: `1px solid ${c.border}`,
            display: 'flex',
            flexDirection: 'column',
            gap: 20,
            ...enter(f, at, { dur: 20, y: 16 }),
        }}
    >
        {SCENARIOS.map((s, i) => (
            <div key={s.label} style={enter(f, at + 4 + i * 2, { dur: 16, y: 8, scale: 1 })}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline' }}>
                    <div style={{ fontSize: 34, fontWeight: s.on ? 600 : 400, color: s.on ? c.fg : c.mutedFg }}>
                        {s.label}
                    </div>
                    <div style={{ fontSize: 38, fontWeight: 600, color: s.on ? c.fg : c.mutedFg }}>{s.value}</div>
                </div>
                <div style={{ marginTop: 10, height: 14, borderRadius: 99, background: c.track }}>
                    <div
                        style={{
                            width: `${(100 * s.v) / 7.2}%`,
                            height: '100%',
                            borderRadius: 99,
                            background: s.on ? c.primary : c.mutedFg,
                            opacity: s.on ? 1 : 0.5,
                            transformOrigin: 'left',
                            transform: `scaleX(${tween(f, at + 12 + i * 4, 30, easeInOut)})`,
                        }}
                    />
                </div>
            </div>
        ))}
    </div>
);
