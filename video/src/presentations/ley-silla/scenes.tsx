import { easeInOut, enter, lerp, tween } from '../../ease';
import { Cursor, FlyDot, easeBack, pop, type Pt, swapIn, swapOut } from '../../kit/fx';
import type { SceneProps } from '../../kit/schema';
import { useTheme } from '../../kit/themes';
import { dur, radius, stagger } from '../../kit/tokens';
import { Button, Card, CheckBadge, Icon, Phone, Tag } from '../../kit/ui';

const Label = ({ children }: { children: string }) => {
    const c = useTheme();
    return <div style={{ fontSize: 26, color: c.mutedFg }}>{children}</div>;
};

// ───────────────────────── 1. Cada puesto, su asiento ─────────────────────────
// C-05: prolongada = más de 3 horas continuas de pie. C-06: Figura 1 da A, B o C. C-01: asientos contra personas por turno.
const QUESTIONS = [
    { label: 'Tiempo de pie continuo', answer: '4 horas' },
    { label: '¿Hay espacio para un asiento en el puesto?', answer: 'Sí' },
    { label: 'Personas por turno', answer: '6' },
] as const;
const RESULTS = [
    { letter: 'A', title: 'En su lugar de trabajo', text: 'Asiento o silla con respaldo, con las características de su espacio.' },
    { letter: 'B', title: 'Cerca de su lugar', text: 'Asiento en un lugar determinado cerca de donde trabaja.' },
    { letter: 'C', title: 'Dentro del centro de trabajo', text: 'Asiento en un lugar determinado dentro del centro.' },
] as const;
const SLOT = (i: number): Pt => ({ x: 982 + i * 84, y: 728 });

export const PositionsScene = ({ f, audioAt }: SceneProps) => {
    const c = useTheme();
    const rowAt = (i: number): number => audioAt + 6 + i * 16;
    const pick = tween(f, audioAt + 62, dur.morph, easeInOut);
    const flyAt = (i: number): number => audioAt + 84 + i * 6;
    const seated = (i: number): number => (i < 5 ? tween(f, flyAt(i) + 24, 8) : 0);
    return (
        <>
            <Card style={{ left: 110, top: 250, width: 760, height: 540, ...enter(f, 2, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 36, fontSize: 40, fontWeight: 600 }}>Puesto: Cajera</div>
                <div style={{ position: 'absolute', left: 44, top: 90 }}>
                    <Label>Figura 1: dónde va el asiento</Label>
                </div>
                {QUESTIONS.map((q, i) => (
                    <div key={q.label} style={{ position: 'absolute', left: 44, right: 44, top: 148 + i * 112, height: 96, borderRadius: radius.control, background: c.muted, border: `1px solid ${c.border}`, boxSizing: 'border-box', display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '0 26px', ...enter(f, audioAt + i * stagger, { dur: dur.enter, y: 10 }) }}>
                        <div style={{ fontSize: 28, fontWeight: 500, maxWidth: 400, lineHeight: 1.2 }}>{q.label}</div>
                        <div style={{ ...pop(f, rowAt(i)), display: 'flex', alignItems: 'center', gap: 12 }}>
                            {i === 0 ? <Tag label="Prolongada" /> : null}
                            <div style={{ fontSize: 34, fontWeight: 600, color: c.primary }}>{q.answer}</div>
                        </div>
                    </div>
                ))}
            </Card>
            <Card style={{ left: 910, top: 250, width: 900, height: 540, ...enter(f, 8, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 36, fontSize: 38, fontWeight: 600 }}>Resultado</div>
                {RESULTS.map((r, i) => {
                    const on = i === 0 ? pick : 0;
                    return (
                        <div key={r.letter} style={{ position: 'absolute', left: 44, right: 44, top: 96 + i * 94, height: 84, borderRadius: radius.control, boxSizing: 'border-box', border: `${on > 0.5 ? 2 : 1}px solid ${on > 0.5 ? c.primary : c.border}`, background: `color-mix(in srgb, ${c.accent} ${on * 100}%, ${c.card})`, display: 'flex', alignItems: 'center', gap: 20, padding: '0 20px', opacity: lerp(0.7, 1, Math.max(on, i === 0 ? 0 : tween(f, audioAt + 4, 10))) }}>
                            <div style={{ width: 52, height: 52, borderRadius: 10, flexShrink: 0, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 28, fontWeight: 600, background: `color-mix(in srgb, ${c.primary} ${on * 100}%, ${c.muted})`, color: on > 0.5 ? c.primaryFg : c.fg }}>{r.letter}</div>
                            <div>
                                <div style={{ fontSize: 28, fontWeight: 600 }}>{r.title}</div>
                                <div style={{ fontSize: 24, color: c.mutedFg, whiteSpace: 'nowrap' }}>{r.text}</div>
                            </div>
                        </div>
                    );
                })}
                <div style={{ position: 'absolute', left: 44, top: 394 }}>
                    <Label>Asientos registrados contra personas por turno</Label>
                </div>
            </Card>
            {Array.from({ length: 6 }, (_, i) => (
                <div key={i} style={{ position: 'absolute', left: SLOT(i).x - 28, top: SLOT(i).y - 28, width: 56, height: 56, borderRadius: 99, boxSizing: 'border-box', border: `2px ${i < 5 ? 'solid' : 'dashed'} ${i < 5 && seated(i) > 0.5 ? c.primary : c.mutedFg}`, background: `color-mix(in srgb, ${c.primary} ${seated(i) * 100}%, transparent)`, display: 'flex', alignItems: 'center', justifyContent: 'center', opacity: tween(f, audioAt + 70, 10), transform: `scale(${1 + 0.15 * Math.sin(seated(i) * Math.PI)})` }}>
                    {i < 5 && seated(i) > 0.5 ? <Icon name="check" size={28} color={c.primaryFg} stroke={3} /> : null}
                </div>
            ))}
            <div style={{ position: 'absolute', left: 1450, top: 698, height: 56, padding: '0 20px', display: 'flex', alignItems: 'center', borderRadius: radius.control, background: c.warnBg, color: c.warnFg, fontSize: 28, fontWeight: 600, ...pop(f, flyAt(5) + 30) }}>Falta 1 asiento</div>
            {Array.from({ length: 6 }, (_, i) => (
                <FlyDot key={i} f={f} start={flyAt(i)} len={24} from={{ x: 800, y: 624 }} to={SLOT(i)} lift={60} size={18} />
            ))}
        </>
    );
};

// ───────────────────────── 2. Pregunta sin señalar a nadie ─────────────────────────
// C-19: encuesta confidencial (SS-02 literal), enlace de un solo uso; se reporta con el mínimo de personas por grupo.
const OPTIONS = ['Sí', 'No', 'No aplica'] as const;
const GROUPS = [
    { name: 'Caja', pct: 78, n: 12 },
    { name: 'Piso de venta', pct: 64, n: 9 },
    { name: 'Bodega', pct: 0, n: 3 },
] as const;
const PHONE_BOX = { left: 300, top: 236, w: 360, h: 574 } as const;
const GROW = (i: number): number => 250 + 150 + i * 112;

export const SurveyScene = ({ f, audioAt }: SceneProps) => {
    const c = useTheme();
    const pick = tween(f, audioAt + 34, dur.micro + 4);
    const sent = tween(f, audioAt + 76, dur.morph, easeInOut);
    const fill = tween(f, audioAt + 104, 56, easeInOut);
    const lockAt = audioAt + 176;
    return (
        <>
            <Phone style={{ left: PHONE_BOX.left, top: PHONE_BOX.top, width: PHONE_BOX.w, height: PHONE_BOX.h, ...enter(f, 2, { dur: dur.scene, x: -30, y: 0 }) }}>
                <div style={{ position: 'absolute', left: 26, right: 26, top: 66, opacity: swapOut(sent) }}>
                    <div style={{ fontSize: 24, color: c.mutedFg }}>Asientos y descansos</div>
                    <div style={{ marginTop: 10, fontSize: 28, fontWeight: 600, lineHeight: 1.25 }}>¿Puede sentarse cuando lo necesita, sin que le llamen la atención ni se vea afectado en su trabajo?</div>
                    {OPTIONS.map((o, i) => {
                        const on = i === 0;
                        return (
                            <div key={o} style={{ marginTop: i === 0 ? 22 : 10, height: 54, borderRadius: radius.control, display: 'flex', alignItems: 'center', padding: '0 18px', fontSize: 26, fontWeight: 500, border: `1px solid ${on && pick > 0.5 ? c.primary : c.border}`, background: on ? `color-mix(in srgb, ${c.primary} ${pick * 100}%, ${c.muted})` : c.muted, color: on && pick > 0.5 ? c.primaryFg : c.fg, ...enter(f, audioAt + 8 + i * stagger, { dur: dur.enter, y: 8 }) }}>
                                {o}
                            </div>
                        );
                    })}
                </div>
                <div style={{ position: 'absolute', left: 26, right: 26, top: 150, opacity: swapIn(sent), textAlign: 'center' }}>
                    <CheckBadge size={96} style={{ margin: '0 auto', transform: `scale(${lerp(0.7, 1, sent)})` }} />
                    <div style={{ marginTop: 26, fontSize: 30, fontWeight: 600, lineHeight: 1.2 }}>Gracias. Su respuesta es anónima.</div>
                    <div style={{ marginTop: 14, fontSize: 24, lineHeight: 1.3, color: c.mutedFg }}>Nadie de su empresa sabrá qué contestó.</div>
                </div>
            </Phone>
            <Card style={{ left: 780, top: 250, width: 1030, height: 540, ...enter(f, 8, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 36, fontSize: 38, fontWeight: 600 }}>Resultados por grupo</div>
                <div style={{ position: 'absolute', left: 44, top: 90 }}>
                    <Label>Pueden sentarse cuando lo necesitan</Label>
                </div>
                <Tag label="Mínimo por grupo: 5, configurable" style={{ position: 'absolute', left: 44, bottom: 36, ...pop(f, lockAt + 14) }} />
            </Card>
            {GROUPS.map((g, i) => {
                const hidden = i === 2 ? tween(f, lockAt, dur.morph, easeInOut) : 0;
                const n = Math.round(g.pct * fill);
                const people = Math.round(g.n * fill);
                return (
                    <div key={g.name} style={{ position: 'absolute', left: 824, top: GROW(i), width: 942, height: 96, borderRadius: radius.control, boxSizing: 'border-box', background: c.muted, border: `1px solid ${c.border}`, ...enter(f, audioAt + 14 + i * stagger, { dur: dur.enter, y: 12 }) }}>
                        <div style={{ position: 'absolute', left: 26, top: 14 }}>
                            <div style={{ fontSize: 32, fontWeight: 600 }}>{g.name}</div>
                            <div style={{ fontSize: 24, color: c.mutedFg, opacity: swapOut(hidden) }}>{`${people} respuestas`}</div>
                        </div>
                        <div style={{ position: 'absolute', left: 330, top: 41, width: 420, height: 14, borderRadius: 99, background: c.track, opacity: swapOut(hidden) }}>
                            <div style={{ height: '100%', borderRadius: 99, background: c.primary, width: i === 2 ? `${(people / 12) * 100}%` : `${n}%` }} />
                        </div>
                        {i < 2 ? <div style={{ position: 'absolute', right: 28, top: 24, fontSize: 44, fontWeight: 600 }}>{`${n} %`}</div> : null}
                        {i === 2 ? (
                            <div style={{ position: 'absolute', left: 330, top: 26, display: 'flex', alignItems: 'center', gap: 14, fontSize: 30, color: c.mutedFg, opacity: swapIn(hidden) }}>
                                <Icon name="lock" size={34} color={c.primary} /> Oculto: menos de 5 respuestas
                            </div>
                        ) : null}
                    </div>
                );
            })}
            {Array.from({ length: 10 }, (_, k) => (
                <FlyDot key={k} f={f} start={audioAt + 84 + k * 3} len={28} from={{ x: PHONE_BOX.left + 180, y: PHONE_BOX.top + 330 }} to={{ x: 1100 + (k % 3) * 20, y: GROW(k % 3) + 48 }} lift={70} size={16} />
            ))}
        </>
    );
};

// ───────────────────────── 3. Un borrador para tu reglamento ─────────────────────────
// C-13: la STPS verificará tres cosas; Humetría propone el texto como borrador editable que revisa el abogado y aprueba la comisión mixta.
const CLAUSES = [
    { title: 'Factores de riesgo', long: 'Factores de riesgo de la bipedestación' },
    { title: 'Pausas', long: 'Pausas a las que se tiene derecho' },
    { title: 'Asientos', long: 'Ubicación y disponibilidad de los asientos' },
] as const;

export const RegulationScene = ({ f, audioAt }: SceneProps) => {
    const c = useTheme();
    const CLICK = audioAt + 84;
    const press = tween(f, CLICK - 3, 6) * (1 - tween(f, CLICK + 3, 6));
    const built = (i: number): number => tween(f, CLICK + 8 + i * 22, 26, easeInOut);
    const note = tween(f, CLICK + 84, dur.enter, easeBack);
    return (
        <>
            <Card style={{ left: 110, top: 250, width: 700, height: 540, ...enter(f, 2, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 36, fontSize: 40, fontWeight: 600 }}>Tres cláusulas</div>
                <div style={{ position: 'absolute', left: 44, top: 90 }}>
                    <Label>Lo que el reglamento debe incluir</Label>
                </div>
                {CLAUSES.map((cl, i) => {
                    const done = built(i) > 0.9 ? tween(f, CLICK + 8 + i * 22 + 24, 8) : 0;
                    return (
                        <div key={cl.title} style={{ position: 'absolute', left: 44, right: 44, top: 138 + i * 96, height: 84, borderRadius: radius.control, boxSizing: 'border-box', background: c.muted, border: `1px solid ${done > 0.5 ? c.primary : c.border}`, display: 'flex', alignItems: 'center', gap: 18, padding: '0 20px', ...enter(f, audioAt + 6 + i * stagger, { dur: dur.enter, y: 10 }) }}>
                            <div style={{ width: 44, height: 44, borderRadius: 99, flexShrink: 0, background: `color-mix(in srgb, ${c.primary} ${done * 100}%, ${c.track})`, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                <div style={{ opacity: done, display: 'flex' }}>
                                    <Icon name="check" size={26} color={c.primaryFg} stroke={3} />
                                </div>
                            </div>
                            <div style={{ fontSize: 28, fontWeight: 500, lineHeight: 1.2 }}>{cl.long}</div>
                        </div>
                    );
                })}
                <Button label="Proponer texto" pressed={press} style={{ position: 'absolute', left: 44, bottom: 36, ...pop(f, audioAt + 30) }} />
            </Card>
            <Card style={{ left: 850, top: 250, width: 960, height: 540, ...enter(f, 8, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 36, display: 'flex', alignItems: 'center', gap: 16, fontSize: 34, fontWeight: 600 }}>
                    <Icon name="file" size={38} color={c.primary} /> Reglamento interior de trabajo
                </div>
                <Tag label="Borrador" style={{ position: 'absolute', right: 44, top: 34 }} />
                {CLAUSES.map((cl, i) => (
                    <div key={cl.title} style={{ position: 'absolute', left: 44, right: 44, top: 108 + i * 110 }}>
                        <div style={{ fontSize: 28, fontWeight: 600, opacity: tween(f, CLICK + 6 + i * 22, 10) }}>{`${i + 1}. ${cl.title}`}</div>
                        {[100, 78].map((w, k) => (
                            <div key={k} style={{ marginTop: 12, height: 14, borderRadius: 99, background: c.track, width: `${w}%` }}>
                                <div style={{ height: '100%', borderRadius: 99, background: c.mutedFg, opacity: 0.55, width: `${built(i) * 100}%` }} />
                            </div>
                        ))}
                    </div>
                ))}
                <div style={{ position: 'absolute', left: 44, right: 44, top: 446, height: 68, borderRadius: radius.control, background: c.warnBg, color: c.warnFg, display: 'flex', alignItems: 'center', padding: '0 22px', fontSize: 24, lineHeight: 1.25, opacity: note, transform: `translateY(${(1 - note) * 14}px)` }}>No es asesoría legal: lo revisa tu abogado y lo aprueba la comisión mixta.</div>
            </Card>
            {CLAUSES.map((cl, i) => (
                <FlyDot key={cl.title} f={f} start={CLICK + 4 + i * 22} len={22} from={{ x: 330, y: 700 }} to={{ x: 900, y: 360 + i * 110 }} lift={80} size={16} />
            ))}
            <Cursor f={f} path={[{ at: audioAt + 52, x: 760, y: 780 }, { at: CLICK - 2, x: 280, y: 718 }]} clicks={[CLICK]} />
        </>
    );
};

// ───────────────────────── 4. De la medida a la evidencia ─────────────────────────
// C-10: medidas con estado, responsable, fecha y evidencia (plan de acción en tres columnas).
const COLS = [
    { title: 'Planeada', x: 110 },
    { title: 'En curso', x: 690 },
    { title: 'Hecha', x: 1270 },
] as const;
const CARD_H = 96;
const slotY = (k: number): number => 250 + 96 + k * (CARD_H + 12);

type Measure = { name: string; sub: string; col: number; slot: number };
const FIXED: readonly Measure[] = [
    { name: 'Calzado ergonómico', sub: 'Vence el 15 dic', col: 0, slot: 0 },
    { name: 'Alternar tareas', sub: 'Vence el 20 dic', col: 0, slot: 1 },
    { name: 'Asientos en estaciones', sub: 'Evidencia subida', col: 2, slot: 0 },
];

const MeasureCard = ({ m, x, y, lift = 0, glow = 0 }: { m: Measure; x: number; y: number; lift?: number; glow?: number }) => {
    const c = useTheme();
    return (
        <div style={{ position: 'absolute', left: x + 28, top: y, width: 484, height: CARD_H, boxSizing: 'border-box', borderRadius: radius.control, background: c.muted, border: `1px solid ${glow > 0.5 ? c.primary : c.border}`, padding: '14px 22px', transform: `translateY(${-lift * 8}px) scale(${1 + 0.03 * lift})`, boxShadow: `0 ${18 * lift}px ${36 * lift}px rgba(0,0,0,${0.3 * lift})` }}>
            <div style={{ fontSize: 30, fontWeight: 600 }}>{m.name}</div>
            <div style={{ marginTop: 4, fontSize: 24, color: m.col === 2 ? c.primary : c.mutedFg }}>{m.sub}</div>
        </div>
    );
};

export const PlanScene = ({ f, audioAt }: SceneProps) => {
    const c = useTheme();
    const CLICK = audioAt + 46;
    const move = tween(f, CLICK + 6, 40, easeInOut);
    const lift = Math.sin(move * Math.PI) * 0.9 + (tween(f, CLICK, 6) - tween(f, CLICK + 46, 8)) * 0.4;
    const arrived = tween(f, CLICK + 46, dur.enter, easeBack);
    const from: Pt = { x: COLS[1].x, y: slotY(0) };
    const to: Pt = { x: COLS[2].x, y: slotY(1) };
    const pisos = lerp(slotY(1), slotY(0), tween(f, CLICK + 10, 24, easeInOut));
    const counts = [2, move > 0.5 ? 1 : 2, move > 0.5 ? 2 : 1];
    const rule = tween(f, audioAt + 128, dur.enter, easeBack);
    return (
        <>
            {COLS.map((col, i) => (
                <div key={col.title}>
                    <Card style={{ left: col.x, top: 250, width: 540, height: 440, ...enter(f, 2 + i * stagger, { dur: dur.scene, y: 18 }) }}>
                        <div style={{ position: 'absolute', left: 28, top: 28, fontSize: 34, fontWeight: 600 }}>{col.title}</div>
                        <div style={{ position: 'absolute', right: 28, top: 28, minWidth: 44, height: 44, borderRadius: 99, background: c.accent, color: c.primary, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 26, fontWeight: 600 }}>{counts[i]}</div>
                    </Card>
                </div>
            ))}
            {FIXED.map((m) => (
                <div key={m.name} style={enter(f, audioAt + 8 + m.col * stagger, { dur: dur.enter, y: 10 })}>
                    <MeasureCard m={m} x={COLS[m.col]?.x ?? 0} y={slotY(m.slot)} />
                </div>
            ))}
            <div style={enter(f, audioAt + 13, { dur: dur.enter, y: 10 })}>
                <MeasureCard m={{ name: 'Pisos amortiguados', sub: 'Vence el 10 ene', col: 1, slot: 1 }} x={COLS[1].x} y={pisos} />
            </div>
            <div style={{ ...enter(f, audioAt + 11, { dur: dur.enter, y: 10 }) }}>
                <MeasureCard m={{ name: 'Pausas activas', sub: move > 0.9 ? 'Evidencia subida' : 'Vence el 12 dic', col: move > 0.9 ? 2 : 1, slot: 0 }} x={lerp(from.x, to.x, move)} y={lerp(from.y, to.y, move)} lift={Math.max(0, lift)} glow={tween(f, CLICK, 6)} />
            </div>
            <div style={{ position: 'absolute', left: to.x + 28 + 300, top: to.y + 22, display: 'flex', alignItems: 'center', gap: 8, height: 44, padding: '0 14px', borderRadius: radius.control, background: c.accent, border: `1px solid ${c.primary}`, fontSize: 24, fontWeight: 500, opacity: arrived, transform: `scale(${lerp(0.6, 1, arrived)})` }}>
                <Icon name="file" size={24} color={c.primary} /> Acta
            </div>
            {Array.from({ length: 5 }, (_, k) => (
                <FlyDot key={k} f={f} start={CLICK + 40 + k * 3} len={22} from={{ x: to.x + 250, y: to.y + 48 }} to={{ x: to.x + 400, y: to.y + 44 }} lift={50} size={14} />
            ))}
            <div style={{ position: 'absolute', left: 110, top: 722, display: 'flex', alignItems: 'center', gap: 16, fontSize: 28, fontWeight: 500, opacity: rule, transform: `translateY(${(1 - rule) * 10}px)` }}>
                <CheckBadge size={44} /> Cada evaluación de nivel medio o alto tiene una medida.
            </div>
            <Cursor f={f} path={[{ at: audioAt + 22, x: 1100, y: 640 }, { at: CLICK - 2, x: from.x + 330, y: from.y + 60 }]} clicks={[CLICK]} />
        </>
    );
};
