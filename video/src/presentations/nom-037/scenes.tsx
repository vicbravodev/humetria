import { easeInOut, enter, lerp, tween } from '../../ease';
import { Cursor, FlyDot, Toast, easeBack, pop, type Pt } from '../../kit/fx';
import type { SceneProps } from '../../kit/schema';
import { useTheme } from '../../kit/themes';
import { dur, radius, stagger } from '../../kit/tokens';
import { Button, Card, CheckBadge, Icon, Phone, Tag } from '../../kit/ui';

const Label = ({ children }: { children: string }) => {
    const c = useTheme();
    return <div style={{ fontSize: 26, color: c.mutedFg }}>{children}</div>;
};

// ───────────────────────── 1. Entra quien pasa del 40 % ─────────────────────────
// C-01: más del 40 % de la jornada del contrato; con 40 % exacto no es teletrabajo, con 41 % sí.
const PEOPLE = [
    { name: 'Mariana L.', initials: 'ML', note: 'Analista', pct: 60, enters: true },
    { name: 'Jorge R.', initials: 'JR', note: '40 % exacto no cuenta', pct: 40, enters: false },
    { name: 'Lucía H.', initials: 'LH', note: 'Con 41 % ya entra', pct: 41, enters: true },
] as const;
const ROWS = { left: 154, w: 1012, top: (i: number): number => 400 + i * 118, h: 104 } as const;
const BAR = { x: 412, w: 294 } as const; // relativos al renglón

export const RosterScene = ({ f, audioAt }: SceneProps) => {
    const c = useTheme();
    const fill = tween(f, audioAt + 14, 40, easeInOut);
    const verdictAt = (i: number): number => audioAt + 58 + i * 6;
    const entering = PEOPLE.map((p, i) => ({ p, i })).filter((r) => r.p.enters);
    const flyAt = (k: number): number => audioAt + 82 + k * 8;
    const arrived = entering.filter((_, k) => f >= flyAt(k) + 22).length;
    return (
        <>
            <Card style={{ left: 110, top: 250, width: 1100, height: 540, ...enter(f, 2, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 40, fontSize: 40, fontWeight: 600 }}>Porcentaje de teletrabajo</div>
                <div style={{ position: 'absolute', left: 44, top: 92 }}>
                    <Label>Sobre la jornada de su contrato</Label>
                </div>
            </Card>
            {PEOPLE.map((p, i) => {
                const shown = Math.round(p.pct * fill);
                const over = p.pct > 40;
                return (
                    <div key={p.name} style={{ position: 'absolute', left: ROWS.left, top: ROWS.top(i), width: ROWS.w, height: ROWS.h, borderRadius: radius.control, background: c.muted, border: `1px solid ${c.border}`, boxSizing: 'border-box', ...enter(f, audioAt + i * stagger, { dur: dur.enter, y: 12 }) }}>
                        <div style={{ position: 'absolute', left: 24, top: 24, width: 56, height: 56, borderRadius: 99, background: c.accent, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 22, fontWeight: 600 }}>{p.initials}</div>
                        <div style={{ position: 'absolute', left: 96, top: 18, width: 300 }}>
                            <div style={{ fontSize: 32, fontWeight: 600 }}>{p.name}</div>
                            <div style={{ fontSize: 24, color: c.mutedFg, marginTop: 2, whiteSpace: 'nowrap' }}>{p.note}</div>
                        </div>
                        <div data-probe-ignore style={{ position: 'absolute', left: BAR.x, top: 46, width: BAR.w, height: 14, borderRadius: 99, background: c.track }}>
                            <div style={{ height: '100%', borderRadius: 99, width: `${p.pct * fill}%`, background: over ? c.primary : c.mutedFg }} />
                            <div style={{ position: 'absolute', left: `${0.4 * BAR.w}px`, top: -12, width: 3, height: 38, borderRadius: 2, background: c.fg, opacity: 0.8 }} />
                            <div style={{ position: 'absolute', left: `${0.4 * BAR.w - 30}px`, top: -42, width: 63, textAlign: 'center', fontSize: 22, color: c.mutedFg }}>40 %</div>
                        </div>
                        <div style={{ position: 'absolute', left: 722, top: 28, width: 100, textAlign: 'right', fontSize: 40, fontWeight: 600, color: over ? c.fg : c.mutedFg }}>{shown} %</div>
                        <div style={{ position: 'absolute', left: 838, top: 26, ...pop(f, verdictAt(i)) }}>
                            {p.enters ? <Tag label="Entra" /> : (
                                <div style={{ display: 'inline-flex', alignItems: 'center', height: 42, padding: '0 14px', borderRadius: radius.control, border: `1px solid ${c.border}`, fontSize: 26, fontWeight: 500, color: c.mutedFg }}>No entra</div>
                            )}
                        </div>
                    </div>
                );
            })}
            <Card style={{ left: 1250, top: 250, width: 560, height: 540, ...enter(f, 8, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 40, fontSize: 38, fontWeight: 600 }}>Listado de teletrabajo</div>
                <div style={{ position: 'absolute', left: 44, top: 100, display: 'flex', alignItems: 'baseline', gap: 14 }}>
                    <div style={{ fontSize: 100, fontWeight: 600, lineHeight: 1, letterSpacing: -2, color: c.primary }}>{arrived}</div>
                    <div style={{ fontSize: 30, color: c.mutedFg }}>personas</div>
                </div>
                {entering.map((r, k) => (
                    <div key={r.p.name} style={{ position: 'absolute', left: 44, top: 250 + k * 88, width: 472, height: 72, borderRadius: radius.control, background: c.muted, border: `1px solid ${c.primary}`, boxSizing: 'border-box', display: 'flex', alignItems: 'center', gap: 16, padding: '0 18px', ...pop(f, flyAt(k) + 20) }}>
                        <div style={{ width: 44, height: 44, borderRadius: 99, background: c.accent, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 19, fontWeight: 600 }}>{r.p.initials}</div>
                        <div style={{ fontSize: 30, fontWeight: 500 }}>{r.p.name}</div>
                    </div>
                ))}
                <Tag label="11 datos por persona" style={{ position: 'absolute', left: 44, bottom: 40, ...pop(f, audioAt + 120) }} />
            </Card>
            {entering.map((r, k) => (
                <FlyDot key={r.p.name} f={f} start={flyAt(k)} len={22} from={{ x: ROWS.left + 838 + 75, y: ROWS.top(r.i) + 47 }} to={{ x: 1300, y: 250 + 250 + k * 88 + 36 }} lift={50} size={20} />
            ))}
        </>
    );
};

// ───────────────────────── 2. Cada lugar, validado ─────────────────────────
// C-11: la persona contesta, no decide. C-12: la Comisión valida el lugar.
const OPTIONS = ['Sí', 'No', 'No aplica'] as const;
const DECISIONS = [
    { label: 'Validado', hint: 'El lugar cumple; vale hasta la siguiente revisión.' },
    { label: 'Validado con plan de acción', hint: 'Cumple, con mejoras por hacer en una fecha compromiso.' },
    { label: 'No validado', hint: 'No cumple todavía; las notas explican por qué.' },
] as const;
const PHONE_BOX = { left: 300, top: 236, w: 360, h: 574 } as const;

export const ChecklistScene = ({ f, audioAt }: SceneProps) => {
    const c = useTheme();
    const pick = tween(f, audioAt + 32, dur.micro + 4);
    const sent = tween(f, audioAt + 72, dur.morph, easeInOut);
    const received = tween(f, audioAt + 106, dur.enter);
    const CLICK = audioAt + 138;
    const valid = tween(f, CLICK + 2, dur.enter, easeBack);
    return (
        <>
            <Phone style={{ left: PHONE_BOX.left, top: PHONE_BOX.top, width: PHONE_BOX.w, height: PHONE_BOX.h, ...enter(f, 2, { dur: dur.scene, x: -30, y: 0 }) }}>
                <div style={{ position: 'absolute', left: 26, right: 26, top: 66, opacity: 1 - sent }}>
                    <div style={{ fontSize: 24, color: c.mutedFg }}>Casa de Mariana L.</div>
                    <div style={{ marginTop: 10, fontSize: 28, fontWeight: 600, lineHeight: 1.25 }}>¿El lugar que usted pudiera tener en mente como lugar de trabajo para realizar Teletrabajo es privado?</div>
                    {OPTIONS.map((o, i) => {
                        const on = i === 0;
                        return (
                            <div key={o} style={{ marginTop: i === 0 ? 24 : 10, height: 56, borderRadius: radius.control, display: 'flex', alignItems: 'center', padding: '0 18px', fontSize: 26, fontWeight: 500, border: `1px solid ${on && pick > 0.5 ? c.primary : c.border}`, background: on ? `color-mix(in srgb, ${c.primary} ${pick * 100}%, ${c.muted})` : c.muted, color: on && pick > 0.5 ? c.primaryFg : c.fg, ...enter(f, audioAt + 8 + i * stagger, { dur: dur.enter, y: 8 }) }}>
                                {o}
                            </div>
                        );
                    })}
                </div>
                <div style={{ position: 'absolute', left: 26, right: 26, top: 130, opacity: sent, textAlign: 'center' }}>
                    <CheckBadge size={96} style={{ margin: '0 auto', transform: `scale(${lerp(0.7, 1, sent)})` }} />
                    <div style={{ marginTop: 26, fontSize: 30, fontWeight: 600, lineHeight: 1.2 }}>Tu lista fue enviada a la Comisión</div>
                    <div style={{ marginTop: 14, fontSize: 24, lineHeight: 1.3, color: c.mutedFg }}>Contestar no te hace responsable del lugar.</div>
                </div>
            </Phone>
            <Card style={{ left: 780, top: 250, width: 1030, height: 540, ...enter(f, 8, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 38, display: 'flex', alignItems: 'center', gap: 16, fontSize: 36, fontWeight: 600 }}>
                    <Icon name="shield" size={40} color={c.primary} /> Comisión de Seguridad e Higiene
                </div>
                <div style={{ position: 'absolute', left: 44, right: 44, top: 118, height: 96, borderRadius: radius.control, background: c.muted, border: `1px solid ${received > 0.5 ? c.primary : c.border}`, boxSizing: 'border-box', display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '0 28px' }}>
                    <div>
                        <div style={{ fontSize: 32, fontWeight: 600 }}>Casa de Mariana L.</div>
                        <div style={{ fontSize: 24, color: c.mutedFg }}>{received > 0.5 ? 'Lista recibida' : 'Esperando la lista'}</div>
                    </div>
                    <div style={{ position: 'relative', width: 300, height: 48 }}>
                        <div style={{ position: 'absolute', right: 0, height: 44, padding: '0 16px', display: 'flex', alignItems: 'center', borderRadius: radius.control, background: c.warnBg, color: c.warnFg, fontSize: 26, fontWeight: 500, opacity: 1 - valid }}>Pendiente de validar</div>
                        <div style={{ position: 'absolute', right: 0, display: 'flex', alignItems: 'center', gap: 10, height: 44, padding: '0 16px', borderRadius: radius.control, background: c.primary, color: c.primaryFg, fontSize: 26, fontWeight: 600, opacity: valid, transform: `scale(${lerp(0.8, 1, valid)})` }}>
                            <Icon name="check" size={26} color={c.primaryFg} stroke={3} /> Validado
                        </div>
                    </div>
                </div>
                {DECISIONS.map((d, i) => {
                    const on = i === 0 ? valid : 0;
                    return (
                        <div key={d.label} style={{ position: 'absolute', left: 44, right: 44, top: 244 + i * 90, height: 80, borderRadius: radius.control, border: `1px solid ${on > 0.5 ? c.primary : c.border}`, background: on > 0.5 ? c.accent : c.card, boxSizing: 'border-box', display: 'flex', alignItems: 'center', gap: 20, padding: '0 24px', ...enter(f, audioAt + 40 + i * stagger, { dur: dur.enter, y: 8 }) }}>
                            <div style={{ width: 30, height: 30, borderRadius: 99, flexShrink: 0, border: `2px solid ${on > 0.5 ? c.primary : c.mutedFg}`, background: `color-mix(in srgb, ${c.primary} ${on * 100}%, transparent)` }} />
                            <div>
                                <div style={{ fontSize: 30, fontWeight: 600 }}>{d.label}</div>
                                <div style={{ fontSize: 24, color: c.mutedFg }}>{d.hint}</div>
                            </div>
                        </div>
                    );
                })}
            </Card>
            {Array.from({ length: 8 }, (_, k) => (
                <FlyDot key={k} f={f} start={audioAt + 78 + k * 3} len={26} from={{ x: PHONE_BOX.left + 180, y: PHONE_BOX.top + 330 }} to={{ x: 900 + (k % 3) * 18, y: 380 + (k % 2) * 16 }} lift={70} size={16} />
            ))}
            <Cursor f={f} path={[{ at: audioAt + 112, x: 1560, y: 700 }, { at: CLICK - 2, x: 1090, y: 540 }]} clicks={[CLICK]} />
        </>
    );
};

// ───────────────────────── 3. Tu política, en 8 preguntas ─────────────────────────
// 8 preguntas cubren los incisos a–l del numeral 5.2; c, f, j y k van literales del Apéndice 1 como texto base.
const QUESTIONS = [
    { inciso: 'e', title: 'Horario y desconexión', prompt: '¿En qué horario trabajan y desde cuándo nadie debe recibir mensajes de trabajo?', options: ['De 9:00 a 18:00', 'De 8:00 a 17:00', 'Horario flexible de 8 horas'], pick: 0 },
    { inciso: 'b', title: 'Contacto con el equipo', prompt: '¿Cada cuánto se reúne el equipo para que nadie se quede aislado?', options: ['Todos los días', 'Una vez por semana', 'Cada 15 días'], pick: 1 },
    { inciso: 'd', title: 'Medios de contacto', prompt: '¿Por qué medios puede la empresa contactar a alguien en teletrabajo?', options: ['Correo de la empresa', 'Teléfono', 'Chat de la empresa'], pick: 0 },
    { inciso: 'h', title: 'Lactancia', prompt: '¿Cómo se respeta la lactancia en teletrabajo?', options: ['Dos descansos de 30 minutos al día', 'Una hora menos de jornada al día'], pick: 0 },
    { inciso: 'a', title: 'Cultura de prevención', prompt: '¿Cada cuánto le hablas a tu equipo de los riesgos del teletrabajo?', options: ['Una vez al año', 'Cada seis meses', 'Cada tres meses'], pick: 1 },
    { inciso: 'g', title: 'Género y vida personal', prompt: '¿Con quién acuerda alguien un horario que le ayude con su vida personal?', options: ['Con su jefe directo', 'Con Recursos Humanos'], pick: 0 },
    { inciso: 'i', title: 'Salud', prompt: '¿Cómo cuida la empresa la salud de quien teletrabaja?', options: ['Pausas activas', 'Apoyo psicológico', 'Campañas de salud'], pick: 0 },
    { inciso: 'l', title: 'Regreso a la oficina', prompt: '¿Con cuánta anticipación se pide volver a la oficina?', options: ['Con 15 días', 'Con 20 días', 'Con 30 días'], pick: 1 },
] as const;
const LETTERS = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l'] as const;
const BASE = new Set(['c', 'f', 'j', 'k']);
/** Cuándo se contesta cada pregunta (frames locales): las primeras se leen, el resto es montaje. */
const slot = (k: number, a: number): number => a + [10, 38, 60, 78, 92, 104, 116, 128][k]!;

export const PolicyScene = ({ f, audioAt }: SceneProps) => {
    const c = useTheme();
    const current = QUESTIONS.reduce((n, _, k) => (f >= slot(k, audioAt) ? k : n), 0);
    const q = QUESTIONS[current] ?? QUESTIONS[0];
    const since = f - slot(current, audioAt);
    const swap = tween(since, 0, 8);
    const picked = tween(since, 10, 8);
    const answered = (k: number): number => tween(f, slot(k, audioAt) + 12, 8);
    const CLICK = audioAt + 142;
    const press = tween(f, CLICK - 3, 6) * (1 - tween(f, CLICK + 3, 6));
    const acks = Math.round(14 * tween(f, CLICK + 12, 50, (t) => t));
    const chipOn = (l: string): number => {
        if (BASE.has(l)) {
            return tween(f, audioAt + 8, 10);
        }
        const k = QUESTIONS.findIndex((x) => x.inciso === l);
        return answered(k);
    };
    return (
        <>
            <Card style={{ left: 110, top: 250, width: 760, height: 540, ...enter(f, 2, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 38, fontSize: 28, color: c.mutedFg, opacity: swap }}>{`Pregunta ${current + 1} de 8 · ${q?.title ?? ''}`}</div>
                <div style={{ position: 'absolute', left: 44, top: 92, display: 'flex', gap: 10 }}>
                    {QUESTIONS.map((x, k) => (
                        <div key={x.inciso} style={{ width: 70, height: 8, borderRadius: 99, background: `color-mix(in srgb, ${c.primary} ${answered(k) * 100}%, ${c.track})` }} />
                    ))}
                </div>
                <div style={{ position: 'absolute', left: 44, right: 44, top: 130, fontSize: 34, fontWeight: 600, lineHeight: 1.22, opacity: swap, transform: `translateY(${(1 - swap) * 10}px)` }}>{q?.prompt}</div>
                {(q?.options ?? []).map((o, i) => {
                    const on = i === q?.pick;
                    return (
                        <div key={o} style={{ position: 'absolute', left: 44, right: 44, top: 296 + i * 70, height: 58, borderRadius: radius.control, display: 'flex', alignItems: 'center', padding: '0 20px', fontSize: 28, fontWeight: 500, border: `1px solid ${on && picked > 0.5 ? c.primary : c.border}`, background: on ? `color-mix(in srgb, ${c.primary} ${picked * 100}%, ${c.muted})` : c.muted, color: on && picked > 0.5 ? c.primaryFg : c.fg, opacity: swap }}>
                            {o}
                        </div>
                    );
                })}
            </Card>
            <Card style={{ left: 910, top: 250, width: 900, height: 540, ...enter(f, 8, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 38, display: 'flex', alignItems: 'center', gap: 16, fontSize: 38, fontWeight: 600 }}>
                    <Icon name="file" size={42} color={c.primary} /> Política de Teletrabajo
                </div>
                <div style={{ position: 'absolute', left: 44, top: 108 }}>
                    <Label>Incisos del numeral 5.2</Label>
                </div>
                <div style={{ position: 'absolute', left: 44, right: 44, top: 152, display: 'grid', gridTemplateColumns: 'repeat(6, 1fr)', gap: 14 }}>
                    {LETTERS.map((l) => {
                        const on = chipOn(l);
                        return (
                            <div key={l} style={{ height: 80, borderRadius: radius.control, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 40, fontWeight: 600, border: `1px solid ${on > 0.5 ? c.primary : c.border}`, background: `color-mix(in srgb, ${c.primary} ${on * (BASE.has(l) ? 22 : 100)}%, ${c.muted})`, color: on > 0.5 && !BASE.has(l) ? c.primaryFg : c.fg, transform: `scale(${1 + 0.1 * Math.sin(on * Math.PI)})` }}>
                                {l}
                            </div>
                        );
                    })}
                </div>
                <Button label="Publicar política" pressed={press} style={{ position: 'absolute', left: 44, top: 384 }} />
                <div style={{ position: 'absolute', left: 372, right: 44, top: 380, opacity: tween(f, CLICK + 6, dur.enter) }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 28, fontWeight: 500 }}>
                        <span>Acuses de recibo</span>
                        <span style={{ color: c.primary, fontWeight: 600 }}>{acks} de 20</span>
                    </div>
                    <div style={{ marginTop: 14, height: 10, borderRadius: 99, background: c.track }}>
                        <div style={{ height: '100%', borderRadius: 99, background: c.primary, width: `${(acks / 20) * 100}%` }} />
                    </div>
                    <div style={{ marginTop: 14, fontSize: 24, color: c.mutedFg }}>Cada persona confirma que la recibió.</div>
                </div>
            </Card>
            {Array.from({ length: 6 }, (_, k) => (
                <FlyDot key={k} f={f} start={CLICK + 4 + k * 6} len={24} from={{ x: 1100, y: 676 }} to={{ x: 1300 + k * 60, y: 650 }} lift={40} size={16} />
            ))}
            <Cursor f={f} path={[{ at: audioAt + 118, x: 1500, y: 780 }, { at: CLICK - 2, x: 1110, y: 690 }]} clicks={[CLICK]} />
        </>
    );
};

// ───────────────────────── 4. Nada vence sin aviso ─────────────────────────
// C-14: la empresa elige la periodicidad (3, 6, 12 o 24 meses; por defecto 12). Avisa cuando una validación está por vencer.
const PERIODS = [3, 6, 12, 24] as const;
const PLACES = [
    { name: 'Casa de Mariana L.', ago: 168 },
    { name: 'Depto. de Jorge R.', ago: 60 },
    { name: 'Casa de Lucía H.', ago: 120 },
] as const;
const CHIPS_TOP = 460;

export const ReviewScene = ({ f, audioAt }: SceneProps) => {
    const c = useTheme();
    const CLICK = audioAt + 52;
    const sw = tween(f, CLICK, dur.morph, easeInOut);
    const period = lerp(12, 6, sw) * 30; // días del periodo, en transición
    const toast = tween(f, CLICK + 54, dur.enter + 4, easeBack);
    const chipX = (i: number): number => 154 + i * 156;
    return (
        <>
            <Card style={{ left: 110, top: 250, width: 700, height: 540, ...enter(f, 2, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 38, fontSize: 40, fontWeight: 600 }}>Verificación periódica</div>
                <div style={{ position: 'absolute', left: 44, right: 44, top: 100, fontSize: 28, lineHeight: 1.3, color: c.mutedFg }}>Cada cuántos meses se vuelve a verificar cada lugar de teletrabajo.</div>
            </Card>
            {PERIODS.map((m, i) => {
                const on = m === 12 ? 1 - sw : m === 6 ? sw : 0;
                return (
                    <div key={m} style={{ position: 'absolute', left: chipX(i), top: CHIPS_TOP, width: 140, height: 110, borderRadius: radius.control, boxSizing: 'border-box', border: `1px solid ${on > 0.5 ? c.primary : c.border}`, background: `color-mix(in srgb, ${c.primary} ${on * 100}%, ${c.muted})`, color: on > 0.5 ? c.primaryFg : c.fg, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', ...enter(f, audioAt + 8 + i * stagger, { dur: dur.enter, y: 10 }) }}>
                        <div style={{ fontSize: 48, fontWeight: 600, lineHeight: 1 }}>{m}</div>
                        <div style={{ fontSize: 24 }}>meses</div>
                    </div>
                );
            })}
            <div style={{ position: 'absolute', left: 154, top: 594, width: 612, fontSize: 26, lineHeight: 1.3, color: c.mutedFg, ...enter(f, audioAt + 30, { dur: dur.enter, y: 8 }) }}>Al vencer, el lugar vuelve a «Pendiente de validar».</div>
            <Tag label="Por defecto: 12 meses" style={{ position: 'absolute', left: 154, top: 706, ...pop(f, audioAt + 34) }} />
            <Card style={{ left: 850, top: 250, width: 960, height: 540, ...enter(f, 8, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 38, fontSize: 40, fontWeight: 600 }}>Validez de cada lugar</div>
            </Card>
            {PLACES.map((p, i) => {
                const days = Math.max(0, Math.round(period - p.ago));
                const frac = Math.max(0.04, Math.min(1, days / period));
                const warn = days <= 30;
                const top = 250 + 120 + i * 112;
                return (
                    <div key={p.name} style={{ position: 'absolute', left: 894, top, width: 872, height: 100, borderRadius: radius.control, boxSizing: 'border-box', background: warn ? c.warnBg : c.muted, border: `1px solid ${warn ? c.warn : c.border}`, ...enter(f, audioAt + 14 + i * stagger, { dur: dur.enter, y: 12 }) }}>
                        <div style={{ position: 'absolute', left: 28, top: 16 }}>
                            <div style={{ fontSize: 32, fontWeight: 600, color: warn ? c.warnFg : c.fg }}>{p.name}</div>
                            <div style={{ fontSize: 24, color: warn ? c.warnFg : c.mutedFg }}>Validado</div>
                        </div>
                        <div style={{ position: 'absolute', left: 330, top: 43, width: 220, height: 14, borderRadius: 99, background: c.track }}>
                            <div style={{ height: '100%', borderRadius: 99, width: `${frac * 100}%`, background: warn ? c.warn : c.primary }} />
                        </div>
                        <div style={{ position: 'absolute', right: 28, top: 30, width: 290, whiteSpace: 'nowrap', textAlign: 'right', fontSize: 30, fontWeight: 600, color: warn ? c.warnFg : c.fg }}>{`vence en ${days} días`}</div>
                    </div>
                );
            })}
            <Toast style={{ left: 894, top: 706, width: 872, height: 76, padding: '0 28px', display: 'flex', alignItems: 'center', gap: 18, opacity: toast, transform: `translateY(${(1 - toast) * 26}px)`, borderColor: c.warn }}>
                <div style={{ transform: `rotate(${Math.sin(toast * 18) * 14 * (1 - toast * 0.2)}deg)`, display: 'flex' }}>
                    <Icon name="bell" size={34} color={c.warn} />
                </div>
                <div style={{ fontSize: 28, fontWeight: 500 }}>Aviso: la validación de Casa de Mariana L. está por vencer</div>
            </Toast>
            {PLACES.map((p, i) => (
                <FlyDot key={p.name} f={f} start={CLICK + 4 + i * 5} len={26} from={{ x: chipX(1) + 70, y: CHIPS_TOP + 55 }} to={{ x: 894 + 465, y: 250 + 120 + i * 112 + 50 } satisfies Pt} lift={60} size={16} />
            ))}
            <Cursor f={f} path={[{ at: audioAt + 28, x: 760, y: 700 }, { at: CLICK - 2, x: chipX(1) + 78, y: CHIPS_TOP + 70 }]} clicks={[CLICK]} />
        </>
    );
};
