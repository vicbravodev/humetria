import type { CSSProperties, ReactNode } from 'react';
import { useCurrentFrame } from 'remotion';
import { Icon, RiskPill } from '../components';
import { easeInOut, enter, exit, tween } from '../ease';
import { T, c, radius, risk, type Risk } from '../theme';

const ANSWERED = 89;
const TOTAL = 120;
const R = 140;
const ARC_LEN = 2 * Math.PI * R * 0.75;

const CATEGORIES: { label: string; level: Risk; pill: string; filled: number }[] = [
    { label: 'Ambiente de trabajo', level: 'low', pill: 'Bajo', filled: 2 },
    { label: 'Factores propios de la actividad', level: 'medium', pill: 'Medio', filled: 3 },
    { label: 'Liderazgo', level: 'null', pill: 'Nulo o despreciable', filled: 1 },
];

const Panel = ({ style, children }: { style: CSSProperties; children: ReactNode }) => (
    <div
        style={{
            position: 'absolute',
            boxSizing: 'border-box',
            padding: 28,
            borderRadius: radius.card,
            background: c.card,
            border: `1px solid ${c.border}`,
            ...style,
        }}
    >
        {children}
    </div>
);

const Label = ({ children }: { children: ReactNode }) => (
    <div style={{ fontSize: 28, fontWeight: 500, color: c.mutedFg }}>{children}</div>
);

export const Results = () => {
    const f = useCurrentFrame();
    if (f < T.s4 + 6 || f > T.s5 + 10) {
        return null;
    }
    const s = T.s4;
    const fill = tween(f, s + 30, 70);
    const n = Math.round(ANSWERED * fill);
    const out = exit(f, T.s5 - 14, { dur: 18, y: -20, scale: 0.98 });

    return (
        <div style={{ position: 'absolute', inset: 0, ...(f >= T.s5 - 14 ? out : {}) }}>
            <Panel style={{ left: 96, top: 172, width: 424, height: 460, ...enter(f, s + 14, { dur: 24, x: 60, y: 0 }) }}>
                <Label>Participación</Label>
                <svg width={320} height={320} viewBox="0 0 320 320" style={{ position: 'absolute', left: 52, top: 84 }}>
                    <path
                        d="M61.01 258.99 A140 140 0 1 1 258.99 258.99"
                        fill="none"
                        stroke={c.track}
                        strokeWidth={22}
                        strokeLinecap="round"
                    />
                    <path
                        d="M61.01 258.99 A140 140 0 1 1 258.99 258.99"
                        fill="none"
                        stroke={c.primary}
                        strokeWidth={22}
                        strokeLinecap="round"
                        strokeDasharray={`${ARC_LEN * (ANSWERED / TOTAL) * fill} ${ARC_LEN}`}
                        opacity={fill > 0.005 ? 1 : 0}
                    />
                </svg>
                <div style={{ position: 'absolute', left: 0, right: 0, top: 176, textAlign: 'center' }}>
                    <div style={{ fontSize: 96, fontWeight: 600, letterSpacing: -3, lineHeight: 1, color: c.fg }}>{n}</div>
                    <div style={{ marginTop: 8, fontSize: 28, color: c.mutedFg }}>de {TOTAL}</div>
                </div>
                <div style={{ position: 'absolute', left: 0, right: 0, top: 374, textAlign: 'center', fontSize: 28, color: c.fg, fontWeight: 500 }}>
                    contestaron
                </div>
            </Panel>

            <Panel style={{ left: 544, top: 172, width: 640, height: 460, ...enter(f, s + 18, { dur: 24, x: 60, y: 0 }) }}>
                <Label>Riesgo por categoría</Label>
                {CATEGORIES.map((cat, k) => (
                    <div
                        key={cat.label}
                        style={{
                            position: 'absolute',
                            left: 28,
                            right: 28,
                            top: 96 + k * 118,
                            ...enter(f, s + 24 + k * 3, { dur: 20, y: 12, scale: 1 }),
                        }}
                    >
                        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', height: 44 }}>
                            <div style={{ fontSize: 30, fontWeight: 500, color: c.fg }}>{cat.label}</div>
                            <RiskPill level={cat.level} label={cat.pill} style={enter(f, s + 70 + k * 5, { dur: 16, y: 0 })} />
                        </div>
                        <div style={{ marginTop: 16, display: 'flex', gap: 8 }}>
                            {[0, 1, 2, 3, 4].map((seg) => (
                                <div key={seg} style={{ flex: 1, height: 12, borderRadius: 99, background: c.track, overflow: 'hidden' }}>
                                    {seg < cat.filled ? (
                                        <div
                                            style={{
                                                width: '100%',
                                                height: '100%',
                                                background: risk[cat.level].solid,
                                                clipPath: `inset(0 ${100 * (1 - tween(f, s + 40 + k * 6 + seg * 5, 14, easeInOut))}% 0 0)`,
                                            }}
                                        />
                                    ) : null}
                                </div>
                            ))}
                        </div>
                    </div>
                ))}
            </Panel>

            <Panel
                style={{
                    left: 96,
                    top: 662,
                    width: 1088,
                    height: 136,
                    display: 'flex',
                    alignItems: 'center',
                    gap: 22,
                    ...enter(f, s + 120, { dur: 24, y: 24 }),
                }}
            >
                <div
                    style={{
                        width: 72,
                        height: 72,
                        borderRadius: radius.control,
                        background: c.accent,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                    }}
                >
                    <Icon name="listCheck" size={36} color={c.primary} />
                </div>
                <div>
                    <div style={{ fontSize: 34, fontWeight: 600, color: c.fg }}>Programa de intervención</div>
                    <div style={{ marginTop: 4, fontSize: 26, color: c.mutedFg }}>Factores propios de la actividad</div>
                </div>
                <div
                    style={{
                        marginLeft: 'auto',
                        height: 48,
                        padding: '0 20px',
                        borderRadius: 999,
                        background: c.primary,
                        color: c.primaryFg,
                        fontSize: 28,
                        fontWeight: 600,
                        display: 'flex',
                        alignItems: 'center',
                        ...enter(f, s + 134, { dur: 16, y: 0 }),
                    }}
                >
                    3 acciones
                </div>
            </Panel>
        </div>
    );
};
