import type { ReactNode } from 'react';
import { easeInOut, enter, lerp, tween } from '../../ease';
import { Cursor, easeBack, pop } from '../../kit/fx';
import type { SceneProps } from '../../kit/schema';
import { useTheme } from '../../kit/themes';
import { dur, radius, stagger } from '../../kit/tokens';
import { CheckBadge, Icon, Tag } from '../../kit/ui';
import { Mascot } from './mascot';

const Q1 = '¿Qué me falta para estar al día en Planta Querétaro?';
const Q2 = '¿Cuánto cuesta el escenario de 5.5 %?';
const A1 = 'Te faltan 31 respuestas de NOM-035 y 3 listas de verificación por validar en NOM-037.';
const A2 = 'El punto medio cuesta $5.6 M el primer año, $1.5 M más que la propuesta de la empresa.';
const THREAD_X = 420;

/** El panel de Humi: columna de la persona a la izquierda, hilo y campo de texto a la derecha. */
const Shell = ({ f, typed, mascot, children }: { f: number; typed?: string; mascot: { dx?: number; dy?: number; think?: number }; children: ReactNode }) => {
    const c = useTheme();
    return (
        <div style={{ position: 'absolute', left: 110, top: 250, width: 1700, height: 540, boxSizing: 'border-box', borderRadius: radius.card, background: c.card, border: `1px solid ${c.border}`, ...enter(f, 2, { dur: dur.scene, y: 18 }) }}>
            <div style={{ position: 'absolute', left: 380, top: 0, width: 1, height: 538, background: c.border }} />
            <div style={{ position: 'absolute', left: 90, top: 56, width: 200, height: 200, borderRadius: 999, background: c.accent, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                <div style={{ marginTop: 12 }}>
                    <Mascot f={f} width={140} {...mascot} />
                </div>
            </div>
            <div style={{ position: 'absolute', left: 20, width: 340, top: 286, textAlign: 'center', fontSize: 44, fontWeight: 600 }}>Humi</div>
            <div style={{ position: 'absolute', left: 20, width: 340, top: 346, textAlign: 'center', fontSize: 26, color: c.mutedFg }}>Asistente de Humetría</div>
            <div style={{ position: 'absolute', left: THREAD_X, right: 44, top: 28, height: 390 }}>{children}</div>
            <div style={{ position: 'absolute', left: THREAD_X, right: 44, top: 440, height: 64, boxSizing: 'border-box', borderRadius: radius.control, background: c.muted, border: `1px solid ${c.border}` }}>
                <div style={{ position: 'absolute', left: 24, top: 16, fontSize: 28, color: typed ? c.fg : c.mutedFg }}>{typed ? typed : 'Pregúntale a Humi…'}</div>
                <div style={{ position: 'absolute', right: 10, top: 9, width: 44, height: 44, borderRadius: 99, background: typed ? c.primary : c.track, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                    <Icon name="arrowUp" size={26} color={typed ? c.primaryFg : c.mutedFg} stroke={2.6} />
                </div>
            </div>
        </div>
    );
};

const Bubble = ({ f, at, text }: { f: number; at: number; text: string }) => {
    const c = useTheme();
    return (
        <div style={{ display: 'flex', justifyContent: 'flex-end', ...enter(f, at, { dur: dur.enter, y: 14, scale: 0.97 }) }}>
            <div style={{ boxSizing: 'border-box', padding: '14px 22px', borderRadius: '14px 14px 4px 14px', background: c.accent, border: `1px solid ${c.border}`, fontSize: 28, lineHeight: 1.3 }}>{text}</div>
        </div>
    );
};

/** Lo que Humi consulta: gira mientras trabaja y se vuelve palomita al terminar. */
const Step = ({ f, at, done, label }: { f: number; at: number; done: number | null; label: string }) => {
    const c = useTheme();
    const d = done === null ? 0 : tween(f, done, 8, easeInOut);
    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: 14, height: 40, fontSize: 26, color: c.mutedFg, ...enter(f, at, { dur: dur.enter, y: 8, scale: 1 }) }}>
            <div style={{ position: 'relative', width: 32, height: 32, flexShrink: 0, overflow: 'hidden' }}>
                <svg width={32} height={32} viewBox="0 0 36 36" style={{ position: 'absolute', inset: 0, opacity: 1 - d }}>
                    <g transform={`rotate(${f * 14} 18 18)`}>
                        <circle cx="18" cy="18" r="14" fill="none" stroke={c.track} strokeWidth="4" />
                        <circle cx="18" cy="18" r="14" fill="none" stroke={c.primary} strokeWidth="4" strokeLinecap="round" strokeDasharray="22 66" />
                    </g>
                </svg>
                <CheckBadge size={32} style={{ position: 'absolute', inset: 0, opacity: d }} />
            </div>
            {label}
        </div>
    );
};

/** Respuesta que se escribe palabra por palabra; todas ocupan su lugar desde el inicio (sin reflujo). */
const Streamed = ({ f, at, text, width }: { f: number; at: number; text: string; width: number }) => {
    const c = useTheme();
    return (
        <div style={{ width, fontSize: 28, lineHeight: 1.4, color: c.fg }}>
            {text.split(' ').map((w, i) => {
                const t = tween(f, at + i * 2.5, 8);
                return (
                    <span key={`${w}-${i}`}>
                        <span style={{ display: 'inline-block', opacity: t, filter: t < 1 ? `blur(${4 * (1 - t)}px)` : 'none', transform: `translateY(${6 * (1 - t)}px)` }}>{w}</span>{' '}
                    </span>
                );
            })}
        </div>
    );
};

const Source = ({ label }: { label: string }) => {
    const c = useTheme();
    return (
        <div style={{ display: 'inline-flex', alignItems: 'center', gap: 10, height: 48, boxSizing: 'border-box', padding: '0 18px 0 14px', borderRadius: radius.control, background: c.muted, border: `1px solid ${c.border}`, fontSize: 26, whiteSpace: 'nowrap' }}>
            <Icon name="file" size={26} color={c.primary} /> {label}
        </div>
    );
};

// ───────────────────────── 1. Pregúntale con tus palabras ─────────────────────────
export const AskScene = ({ f, audioAt }: SceneProps) => {
    const type0 = audioAt + 20;
    const send = audioAt + 108;
    const n = Math.round(Q1.length * tween(f, type0, 78, (t) => t));
    const typed = f >= type0 && f < send ? Q1.slice(0, n) : '';
    const think = tween(f, send + 4, 8) ;
    return (
        <Shell f={f} typed={typed} mascot={{ dx: lerp(0, 3, tween(f, type0 - 6, 10)), dy: lerp(0, 13, tween(f, type0 - 6, 10)) * (1 - tween(f, send, 8)), think }}>
            {f >= send ? <Bubble f={f} at={send} text={Q1} /> : null}
            {f >= send + 22 ? (
                <div style={{ marginTop: 18 }}>
                    <Step f={f} at={send + 22} done={null} label="Consultando el avance de campañas" />
                </div>
            ) : null}
        </Shell>
    );
};

// ───────────────────────── 2. Responde con datos y fuentes ─────────────────────────
export const AnswerScene = ({ f, audioAt }: SceneProps) => {
    const look = Math.sin(f / 9) * 11;
    const working = tween(f, 2, 8) * (1 - tween(f, audioAt + 54, 10));
    return (
        <Shell f={f} mascot={{ dx: look * working, dy: -9 * working, think: working }}>
            <Bubble f={f} at={2} text={Q1} />
            <div style={{ marginTop: 14 }}>
                <Step f={f} at={audioAt + 6} done={audioAt + 30} label="Consulté el avance de campañas" />
                <Step f={f} at={audioAt + 24} done={audioAt + 50} label="Consulté los pendientes" />
            </div>
            <div style={{ marginTop: 14 }}>
                <Streamed f={f} at={audioAt + 56} text={A1} width={1100} />
            </div>
            <div style={{ marginTop: 18, display: 'flex', gap: 14 }}>
                <div style={pop(f, audioAt + 118)}>
                    <Source label="Campaña NOM-035 · Planta Querétaro" />
                </div>
                <div style={pop(f, audioAt + 130)}>
                    <Source label="NOM-037 · Listas de verificación" />
                </div>
            </div>
        </Shell>
    );
};

// ───────────────────────── 3. Explica escenarios ─────────────────────────
const SCENARIOS = [
    { label: 'Empresa · 4 %', value: '$4.1 M', v: 4.1, on: false },
    { label: 'Punto medio · 5.5 %', value: '$5.6 M', v: 5.6, on: true },
    { label: 'Sindicato · 7 %', value: '$7.2 M', v: 7.2, on: false },
] as const;

export const ScenarioScene = ({ f, audioAt }: SceneProps) => {
    const c = useTheme();
    const CLICK = audioAt + 128;
    const press = tween(f, CLICK - 3, 6) * (1 - tween(f, CLICK + 3, 6));
    const working = tween(f, 2, 8) * (1 - tween(f, audioAt + 34, 10));
    return (
        <>
        <Shell f={f} mascot={{ dx: Math.sin(f / 9) * 11 * working, dy: -9 * working, think: working }}>
            <div style={{ position: 'absolute', left: 0, top: 0, width: 620 }}>
                <Bubble f={f} at={2} text={Q2} />
                <div style={{ marginTop: 14 }}>
                    <Step f={f} at={audioAt + 6} done={audioAt + 30} label="Consulté el escenario de Compensación" />
                </div>
                <div style={{ marginTop: 14 }}>
                    <Streamed f={f} at={audioAt + 36} text={A2} width={620} />
                </div>
                <div style={{ marginTop: 18, height: 56, ...pop(f, audioAt + 100) }}>
                    <div style={{ display: 'inline-flex', alignItems: 'center', gap: 12, height: 56, boxSizing: 'border-box', padding: '0 24px', borderRadius: radius.control, background: c.primary, color: c.primaryFg, fontSize: 26, fontWeight: 600, transform: `scale(${1 - 0.04 * press})` }}>
                        Abrir el escenario <Icon name="arrowRight" size={26} color={c.primaryFg} stroke={2.4} />
                    </div>
                </div>
            </div>
            <div style={{ position: 'absolute', left: 690, right: 0, top: 0, boxSizing: 'border-box', padding: '22px 26px 24px', borderRadius: radius.card, background: c.muted, border: `1px solid ${c.border}`, height: 340, ...enter(f, audioAt + 40, { dur: 20, y: 14 }) }}>
                {SCENARIOS.map((s, i) => (
                    <div key={s.label} style={{ position: 'absolute', left: 26, right: 26, top: 22 + i * 100, ...enter(f, audioAt + 44 + i * stagger, { dur: dur.enter, y: 8, scale: 1 }) }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline' }}>
                            <div style={{ fontSize: 28, fontWeight: s.on ? 600 : 400, color: s.on ? c.fg : c.mutedFg }}>{s.label}</div>
                            <div style={{ fontSize: 32, fontWeight: 600, color: s.on ? c.fg : c.mutedFg }}>{s.value}</div>
                        </div>
                        <div style={{ marginTop: 12, height: 14, borderRadius: 99, background: c.track }}>
                            <div style={{ width: `${(100 * s.v) / 7.2}%`, height: '100%', borderRadius: 99, background: s.on ? c.primary : c.mutedFg, opacity: s.on ? 1 : 0.5, transformOrigin: 'left', transform: `scaleX(${tween(f, audioAt + 52 + i * 4, 30, easeInOut)})` }} />
                        </div>
                    </div>
                ))}
            </div>
        </Shell>
        <Cursor f={f} path={[{ at: audioAt + 104, x: 1000, y: 700 }, { at: CLICK - 2, x: 690, y: 548 }]} clicks={[CLICK]} />
        </>
    );
};

// ───────────────────────── 4. Solo lee y explica ─────────────────────────
const LIMITS = [
    { icon: 'lock', text: 'No cambia datos: solo lee y explica', at: 36 },
    { icon: 'eyeOff', text: 'Nunca ve respuestas individuales ni la Guía I', at: 96 },
    { icon: 'lock', text: 'Solo habla de la empresa en la que estás', at: 150 },
] as const;

export const LimitsScene = ({ f, audioAt }: SceneProps) => {
    const c = useTheme();
    return (
        <Shell f={f} mascot={{}}>
            {LIMITS.map((l) => {
                const g = tween(f, audioAt + l.at, dur.enter, easeBack);
                return (
                    <div key={l.text} style={{ position: 'absolute', left: 0, right: 0, top: LIMITS.indexOf(l) * 104, height: 88, boxSizing: 'border-box', borderRadius: radius.control, background: c.muted, border: `1px solid ${g > 0.5 ? c.primary : c.border}`, display: 'flex', alignItems: 'center', gap: 20, padding: '0 24px', opacity: g, transform: `translateY(${(1 - g) * 12}px)` }}>
                        <div style={{ width: 48, height: 48, borderRadius: 99, flexShrink: 0, display: 'flex', alignItems: 'center', justifyContent: 'center', background: c.accent }}>
                            <Icon name={l.icon} size={28} color={c.primary} />
                        </div>
                        <div style={{ fontSize: 30, fontWeight: 500 }}>{l.text}</div>
                    </div>
                );
            })}
            <div style={{ position: 'absolute', left: 0, top: 330, ...pop(f, audioAt + 176) }}>
                <Tag label="Se apaga por empresa" />
            </div>
        </Shell>
    );
};
