import { easeInOut, enter, lerp, tween } from '../../ease';
import { Cursor, FlyDot, easeBack, pop, swapIn, swapOut } from '../../kit/fx';
import { QrCode } from '../../kit/qr';
import type { SceneProps } from '../../kit/schema';
import { useTheme } from '../../kit/themes';
import { dur, radius, stagger } from '../../kit/tokens';
import { Button, Card, CheckBadge, Icon, Phone, Tag } from '../../kit/ui';

/** Huella dactilar genérica: crestas concéntricas que se «leen» (pulso). */
const Fingerprint = ({ size, pulse }: { size: number; pulse: number }) => {
    const c = useTheme();
    return (
        <svg width={size} height={size} viewBox="0 0 100 100" fill="none" stroke={c.primary} strokeWidth={3.4} strokeLinecap="round">
            {[10, 17, 24, 31, 38].map((r, i) => (
                <circle key={r} cx={50} cy={52} r={r} strokeDasharray={`${34 + i * 9} ${14 + i * 4}`} transform={`rotate(${i * 38 - 70} 50 52)`} opacity={0.35 + 0.65 * Math.max(0, Math.min(1, pulse * 5 - i * 0.7))} />
            ))}
        </svg>
    );
};

// ───────────────────────── 1. Marca en un toque ─────────────────────────
// Valida el lugar con el QR del centro (o la geocerca) y confirma con huella; sin correo ni contraseña.
const PHONE = { left: 760, top: 236, w: 360, h: 574 } as const;

export const MarkScene = ({ f, audioAt }: SceneProps) => {
    const c = useTheme();
    const beam = tween(f, audioAt + 8, 36, easeInOut);
    const sweep = beam > 0 && beam < 1 ? Math.abs(Math.sin(beam * Math.PI * 1.5)) : 0;
    const TAP = audioAt + 70;
    const press = tween(f, TAP, 6) * (1 - tween(f, TAP + 6, 6));
    const scan = tween(f, TAP + 16, dur.enter);
    const done = tween(f, audioAt + 130, 14, easeInOut);
    const read = tween(f, TAP + 14, 46);
    const ready = 1 - tween(f, TAP + 8, 8);
    const placeAt = audioAt + 54;
    return (
        <>
            <Card style={{ left: 110, top: 250, width: 560, height: 540, ...enter(f, 2, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 34, fontSize: 34, fontWeight: 600 }}>Código QR del centro</div>
                <div style={{ position: 'absolute', left: 130, top: 112, width: 300, height: 300, boxSizing: 'border-box', padding: 14, borderRadius: radius.card, border: `3px solid ${c.fg}`, background: c.card }}>
                    <QrCode size={268} />
                </div>
                <div style={{ position: 'absolute', left: 44, bottom: 40, fontSize: 30, fontWeight: 500 }}>Oficinas Monterrey</div>
            </Card>
            {beam > 0 && beam < 1 ? <div style={{ position: 'absolute', left: 240, top: 372 + sweep * 270, width: 280, height: 4, borderRadius: 4, background: c.primary, boxShadow: `0 0 22px ${c.primary}` }} /> : null}
            <Phone time="08:58" style={{ left: PHONE.left, top: PHONE.top, width: PHONE.w, height: PHONE.h, ...enter(f, 12, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 26, right: 26, top: 70, opacity: ready, textAlign: 'center' }}>
                    <div style={{ fontSize: 24, color: c.mutedFg }}>Oficinas Monterrey</div>
                    <div style={{ marginTop: 14, fontSize: 88, fontWeight: 600, lineHeight: 1, letterSpacing: -2 }}>08:58</div>
                    <div style={{ marginTop: 8, fontSize: 26, color: c.mutedFg }}>lunes 4 de enero</div>
                    <div style={{ marginTop: 96, height: 76, borderRadius: radius.card, background: c.primary, color: c.primaryFg, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 32, fontWeight: 600, transform: `scale(${1 - 0.05 * press})` }}>Marcar entrada</div>
                </div>
                <div style={{ position: 'absolute', left: 26, right: 26, top: 120, opacity: scan * (1 - tween(f, audioAt + 124, 8)), textAlign: 'center' }}>
                    <div style={{ display: 'flex', justifyContent: 'center', transform: `scale(${1 + 0.06 * Math.sin(read * Math.PI * 4)})` }}>
                        <Fingerprint size={190} pulse={read} />
                    </div>
                    <div style={{ marginTop: 18, fontSize: 30, fontWeight: 600 }}>Confirma con tu huella</div>
                </div>
                <div style={{ position: 'absolute', left: 26, right: 26, top: 130, opacity: done, textAlign: 'center' }}>
                    <CheckBadge size={96} style={{ margin: '0 auto', transform: `scale(${lerp(0.7, 1, done)})` }} />
                    <div style={{ marginTop: 24, fontSize: 32, fontWeight: 600 }}>Entrada registrada</div>
                    <div style={{ marginTop: 8, fontSize: 40, fontWeight: 600, color: c.primary }}>08:58</div>
                </div>
            </Phone>
            <Card style={{ left: 1180, top: 250, width: 630, height: 540, ...enter(f, 20, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 34, fontSize: 38, fontWeight: 600 }}>Hoy</div>
                {[
                    { text: 'Lugar validado', at: placeAt + 20 },
                    { text: 'Huella confirmada', at: audioAt + 124 },
                ].map((t, i) => (
                    <div key={t.text} style={{ position: 'absolute', left: 44, top: 104 + i * 74, ...pop(f, t.at) }}>
                        <Tag label={t.text} />
                    </div>
                ))}
                <div style={{ position: 'absolute', left: 44, right: 44, top: 270, height: 92, boxSizing: 'border-box', borderRadius: radius.control, background: c.muted, border: `1px solid ${done > 0.5 ? c.primary : c.border}`, display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '0 26px', opacity: done, transform: `translateY(${(1 - done) * 12}px)` }}>
                    <div style={{ fontSize: 32, fontWeight: 600 }}>Entrada 08:58</div>
                    <Icon name="lock" size={32} color={c.primary} />
                </div>
                <div style={{ position: 'absolute', left: 44, bottom: 40, display: 'flex', alignItems: 'center', gap: 12, fontSize: 26, color: c.mutedFg, ...pop(f, audioAt + 150) }}>
                    <Icon name="shield" size={30} color={c.primary} /> Sin correo ni contraseña
                </div>
            </Card>
            {Array.from({ length: 8 }, (_, k) => (
                <FlyDot key={k} f={f} start={audioAt + 40 + k * 3} len={26} from={{ x: 390, y: 520 }} to={{ x: PHONE.left + 180, y: PHONE.top + 300 }} lift={60} size={16} />
            ))}
        </>
    );
};

// ───────────────────────── 2. Un registro que no se altera ─────────────────────────
// El registro es inalterable; una corrección se suma, con su motivo, y el original se queda.
const MARKS = [
    { label: 'Entrada', time: '08:58' },
    { label: 'Salida a comer', time: '14:02' },
    { label: 'Regreso de comer', time: '14:47' },
] as const;
const MROW = (i: number): number => 250 + 112 + i * 96;

export const RecordScene = ({ f, audioAt }: SceneProps) => {
    const c = useTheme();
    const TRY = audioAt + 56;
    const ASK = audioAt + 112;
    const shake = Math.sin((f - TRY) * 1.9) * 10 * (tween(f, TRY, 2) * (1 - tween(f, TRY + 16, 4)));
    const denied = tween(f, TRY + 4, dur.enter, easeBack) * (1 - tween(f, TRY + 50, dur.enter));
    const press = tween(f, ASK - 3, 6) * (1 - tween(f, ASK + 3, 6));
    const fix = tween(f, ASK + 10, dur.enter, easeBack);
    const stepOn = (i: number): number => tween(f, [audioAt + 40, ASK, ASK + 20][i] ?? 0, dur.enter, easeBack);
    return (
        <>
            <Card style={{ left: 110, top: 250, width: 860, height: 540, ...enter(f, 2, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 34, fontSize: 38, fontWeight: 600 }}>Jornada del lunes</div>
                <Button label="Pedir corrección" pressed={press} style={{ position: 'absolute', right: 44, top: 22, ...pop(f, audioAt + 76) }} />
            </Card>
            {MARKS.map((m, i) => (
                <div key={m.label} style={{ position: 'absolute', left: 154, top: MROW(i), width: 772, height: 84, boxSizing: 'border-box', borderRadius: radius.control, background: c.muted, border: `1px solid ${i === 1 && denied > 0.2 ? c.warn : c.border}`, display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '0 26px', transform: `translateX(${i === 1 ? shake : 0}px)`, ...enter(f, audioAt + 4 + i * stagger, { dur: dur.enter, y: 10 }) }}>
                    <div style={{ display: 'flex', alignItems: 'baseline', gap: 18 }}>
                        <div style={{ fontSize: 32, fontWeight: 600 }}>{m.label}</div>
                        <div style={{ fontSize: 32, color: c.mutedFg }}>{m.time}</div>
                    </div>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 10, fontSize: 26, color: c.mutedFg }}>
                        {i === 1 && fix > 0.5 ? <Tag label="Original" /> : null}
                        <Icon name="lock" size={30} color={i === 1 && denied > 0.2 ? c.warn : c.primary} />
                    </div>
                </div>
            ))}
            <div style={{ position: 'absolute', left: 154, top: MROW(2) + 96, width: 772, height: 84, boxSizing: 'border-box', borderRadius: radius.control, background: c.accent, border: `1px solid ${c.primary}`, display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '0 26px', opacity: fix, transform: `translateY(${(1 - fix) * 14}px)` }}>
                <div style={{ display: 'flex', alignItems: 'baseline', gap: 14, whiteSpace: 'nowrap' }}>
                    <div style={{ fontSize: 28, fontWeight: 600 }}>Corrección</div>
                    <div style={{ fontSize: 26, color: c.mutedFg }}>Salida a comer 14:00</div>
                </div>
                <Tag label="Motivo: olvidé marcar" />
            </div>
            <div style={{ position: 'absolute', left: 260, top: MROW(1) - 40, height: 44, padding: '0 16px', display: 'flex', alignItems: 'center', gap: 10, borderRadius: radius.control, background: c.warnBg, color: c.warnFg, fontSize: 26, fontWeight: 600, opacity: denied, transform: `translateY(${(1 - denied) * 8}px)` }}>No se puede alterar</div>
            <Card style={{ left: 1010, top: 250, width: 800, height: 540, ...enter(f, 8, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 34, fontSize: 38, fontWeight: 600 }}>Cómo se corrige</div>
                {['Se pide con su motivo', 'Se decide', 'Se suma: el original no cambia'].map((s, i) => {
                    const on = stepOn(i);
                    return (
                        <div key={s} style={{ position: 'absolute', left: 44, right: 44, top: 112 + i * 112, height: 96, boxSizing: 'border-box', borderRadius: radius.control, background: c.muted, border: `1px solid ${on > 0.5 ? c.primary : c.border}`, display: 'flex', alignItems: 'center', gap: 20, padding: '0 24px', opacity: lerp(0.5, 1, on) }}>
                            <div style={{ width: 52, height: 52, borderRadius: 99, flexShrink: 0, background: `color-mix(in srgb, ${c.primary} ${on * 100}%, ${c.track})`, color: on > 0.5 ? c.primaryFg : c.fg, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 28, fontWeight: 600 }}>{i + 1}</div>
                            <div style={{ fontSize: 30, fontWeight: 500 }}>{s}</div>
                        </div>
                    );
                })}
            </Card>
            <Cursor f={f} path={[{ at: audioAt + 40, x: 880, y: 740 }, { at: TRY - 2, x: 560, y: MROW(1) + 42 }, { at: ASK - 2, x: 780, y: 300 }]} clicks={[TRY, ASK]} />
        </>
    );
};

// ───────────────────────── 3. Topes a la vista ─────────────────────────
// Semana de ejemplo en 2027: máximo semanal ordinario de 46 h y hasta 9 h extra (arts. 59 y 66, transitorios).
const DAYS = [
    { d: 'L', ord: 8, extra: 0 },
    { d: 'M', ord: 8, extra: 0 },
    { d: 'M', ord: 8, extra: 1 },
    { d: 'J', ord: 8, extra: 0 },
    { d: 'V', ord: 8, extra: 2 },
    { d: 'S', ord: 0, extra: 0 },
    { d: 'D', ord: 0, extra: 0 },
] as const;
const WBASE = 250 + 440;

export const WeekScene = ({ f, audioAt }: SceneProps) => {
    const c = useTheme();
    const col = (i: number): number => tween(f, audioAt + 10 + i * 7, 30, easeInOut);
    const CLICK = audioAt + 126;
    const press = tween(f, CLICK - 3, 6) * (1 - tween(f, CLICK + 3, 6));
    const confirmed = tween(f, CLICK + 6, dur.morph, easeBack);
    const total = 43 * tween(f, audioAt + 30, 70, (t) => t);
    const extra = 3 * tween(f, audioAt + 30, 70, (t) => t);
    return (
        <>
            <Card style={{ left: 110, top: 250, width: 1100, height: 540, ...enter(f, 2, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 34, fontSize: 38, fontWeight: 600 }}>Semana del 4 al 10 de enero</div>
                <div style={{ position: 'absolute', left: 44, right: 44, top: 440, height: 2, background: c.border }} />
            </Card>
            {DAYS.map((d, i) => {
                const g = col(i);
                const k = 26;
                const ho = d.ord * k * g;
                const he = d.extra * k * g;
                const x = 154 + i * 146;
                return (
                    <div key={i}>
                        <div style={{ position: 'absolute', left: x, top: WBASE - ho - he - 48, width: 100, textAlign: 'center', fontSize: 30, fontWeight: 600, opacity: g * (d.ord + d.extra > 0 ? 1 : 0) }}>{`${d.ord + d.extra} h`}</div>
                        <div style={{ position: 'absolute', left: x, top: WBASE - ho, width: 100, height: ho, borderRadius: d.extra > 0 ? '0 0 4px 4px' : '10px 10px 4px 4px', background: c.primary }} />
                        <div style={{ position: 'absolute', left: x, top: WBASE - ho - he, width: 100, height: he, borderRadius: '10px 10px 0 0', background: c.warn }} />
                        <div style={{ position: 'absolute', left: x, top: WBASE + 12, width: 100, textAlign: 'center', fontSize: 26, color: c.mutedFg }}>{d.d}</div>
                    </div>
                );
            })}
            <Card style={{ left: 1250, top: 250, width: 560, height: 540, ...enter(f, 8, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 36, top: 22, fontSize: 34, fontWeight: 600 }}>Resumen</div>
                {[
                    ['Horas ordinarias', '40:00'],
                    ['Horas extra dobles', '3:00'],
                    ['Horas extra triples', '0:00'],
                    ['En su día de descanso', '0:00'],
                ].map(([k, v], i) => (
                    <div key={k} style={{ position: 'absolute', left: 36, right: 36, top: 72 + i * 46, height: 40, display: 'flex', alignItems: 'center', justifyContent: 'space-between', ...enter(f, audioAt + 20 + i * stagger, { dur: dur.enter, y: 8 }) }}>
                        <div style={{ fontSize: 26, color: c.mutedFg }}>{k}</div>
                        <div style={{ fontSize: 30, fontWeight: 600 }}>{v}</div>
                    </div>
                ))}
                <div style={{ position: 'absolute', left: 36, top: 266, ...pop(f, audioAt + 50) }}>
                    <Tag label="Topes de 2027" />
                </div>
                {[
                    ['Semana', `${Math.round(total)} de 46 h`, total / 46, c.primary],
                    ['Horas extra', `${extra.toFixed(0)} de 9 h`, extra / 9, c.warn],
                ].map(([k, v, frac, color], i) => (
                    <div key={String(k)} style={{ position: 'absolute', left: 36, right: 36, top: 322 + i * 52 }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 24 }}>
                            <span style={{ color: c.mutedFg }}>{String(k)}</span>
                            <span style={{ fontWeight: 600 }}>{String(v)}</span>
                        </div>
                        <div style={{ marginTop: 6, height: 10, borderRadius: 99, background: c.track }}>
                            <div style={{ height: '100%', borderRadius: 99, background: String(color), width: `${Number(frac) * 100}%` }} />
                        </div>
                    </div>
                ))}
                <div style={{ position: 'absolute', left: 36, right: 36, bottom: 24, height: 64 }}>
                    <div style={{ position: 'absolute', inset: 0, opacity: swapOut(confirmed) }}>
                        <Button label="Confirmar mi semana" pressed={press} style={{ width: '100%', justifyContent: 'center', boxSizing: 'border-box' }} />
                    </div>
                    <div style={{ position: 'absolute', inset: 0, display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 14, borderRadius: radius.control, background: c.accent, border: `1px solid ${c.primary}`, fontSize: 28, fontWeight: 600, opacity: swapIn(confirmed), transform: `scale(${lerp(0.9, 1, confirmed)})` }}>
                        <CheckBadge size={36} /> Semana confirmada
                    </div>
                </div>
            </Card>
            <Cursor f={f} path={[{ at: audioAt + 96, x: 1700, y: 600 }, { at: CLICK - 2, x: 1540, y: 748 }]} clicks={[CLICK]} />
        </>
    );
};

// ───────────────────────── 4. Reporte con un botón ─────────────────────────
// El reporte para la autoridad (LFT 132-XXXIV); cada semana la confirma la persona: el silencio no es conformidad.
export const ReportScene = ({ f, audioAt }: SceneProps) => {
    const c = useTheme();
    const CLICK = audioAt + 30;
    const press = tween(f, CLICK - 3, 6) * (1 - tween(f, CLICK + 3, 6));
    const built = tween(f, CLICK + 6, 40, easeInOut);
    const tiles = [
        { label: 'Personas', value: Math.round(120 * built) },
        { label: 'Semanas confirmadas', value: Math.round(118 * built) },
        { label: 'Correcciones', value: Math.round(6 * built) },
    ];
    return (
        <>
            <Card style={{ left: 110, top: 250, width: 700, height: 540, ...enter(f, 2, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 34, fontSize: 38, fontWeight: 600 }}>Reporte</div>
                {[
                    ['Periodo', 'Enero de 2027'],
                    ['Centro', 'Todos los centros'],
                ].map(([k, v], i) => (
                    <div key={k} style={{ position: 'absolute', left: 44, right: 44, top: 112 + i * 112, height: 96, boxSizing: 'border-box', borderRadius: radius.control, background: c.muted, border: `1px solid ${c.border}`, padding: '16px 24px', ...enter(f, audioAt + 6 + i * stagger, { dur: dur.enter, y: 10 }) }}>
                        <div style={{ fontSize: 24, color: c.mutedFg }}>{k}</div>
                        <div style={{ marginTop: 2, fontSize: 32, fontWeight: 600 }}>{v}</div>
                    </div>
                ))}
                <Button label="Generar reporte" pressed={press} style={{ position: 'absolute', left: 44, bottom: 40, ...pop(f, audioAt + 12) }} />
            </Card>
            <Card style={{ left: 850, top: 250, width: 960, height: 540, ...enter(f, 8, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 34, display: 'flex', alignItems: 'center', gap: 16, fontSize: 38, fontWeight: 600 }}>
                    <Icon name="file" size={42} color={c.primary} /> Reporte para la autoridad
                </div>
                {tiles.map((t, i) => (
                    <div key={t.label} style={{ position: 'absolute', left: 44 + i * 298, top: 112, width: 274, height: 176, boxSizing: 'border-box', borderRadius: radius.card, background: c.muted, border: `1px solid ${c.border}`, padding: '20px 24px', opacity: tween(f, CLICK + 8 + i * 6, dur.enter) }}>
                        <div style={{ fontSize: 72, fontWeight: 600, lineHeight: 1, color: c.primary }}>{t.value}</div>
                        <div style={{ marginTop: 8, fontSize: 24, color: c.mutedFg }}>{t.label}</div>
                    </div>
                ))}
                {[0, 1, 2].map((r) => (
                    <div key={r} style={{ position: 'absolute', left: 44, right: 44, top: 318 + r * 50, height: 38, borderRadius: radius.control, background: c.track, opacity: 0.6 * tween(f, CLICK + 20 + r * 8, dur.enter) }}>
                        <div style={{ height: '100%', borderRadius: radius.control, background: c.mutedFg, opacity: 0.35, width: `${built * (90 - r * 14)}%` }} />
                    </div>
                ))}
                <div style={{ position: 'absolute', left: 44, right: 44, bottom: 30, display: 'flex', alignItems: 'center', gap: 14, fontSize: 26, color: c.mutedFg, whiteSpace: 'nowrap', ...pop(f, CLICK + 56) }}>
                    <CheckBadge size={36} /> La persona confirma cada semana: el silencio no es conformidad.
                </div>
            </Card>
            {Array.from({ length: 6 }, (_, k) => (
                <FlyDot key={k} f={f} start={CLICK + 4 + k * 3} len={24} from={{ x: 300, y: 720 }} to={{ x: 1000 + k * 60, y: 300 }} lift={80} size={16} />
            ))}
            <Cursor f={f} path={[{ at: audioAt + 8, x: 700, y: 700 }, { at: CLICK - 2, x: 270, y: 746 }]} clicks={[CLICK]} />
        </>
    );
};
