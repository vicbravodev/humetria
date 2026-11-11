import { easeInOut, enter, lerp, tween } from '../../ease';
import { Cursor, FlyDot, easeBack, pop, swapIn, swapOut } from '../../kit/fx';
import type { SceneProps } from '../../kit/schema';
import { useTheme } from '../../kit/themes';
import { dur, radius, stagger } from '../../kit/tokens';
import { Button, Card, CheckBadge, Icon, Phone, Tag } from '../../kit/ui';

/** Las cifras de los capítulos 3 y 4 son de demostración; se rotulan arriba a la derecha. */
const SampleTag = ({ f, at }: { f: number; at: number }) => (
    <div style={{ position: 'absolute', right: 110, top: 142, ...pop(f, at) }}>
        <Tag label="Cifras de ejemplo" />
    </div>
);

const Label = ({ children }: { children: string }) => {
    const c = useTheme();
    return <div style={{ fontSize: 26, color: c.mutedFg }}>{children}</div>;
};

// ───────────────────────── 1. Una encuesta, un enlace ─────────────────────────
// Escala de acuerdo de 5 puntos; toda pregunta se puede dejar sin contestar. Qué se guarda de cada respuesta (README del módulo).
const PBOX = { left: 300, top: 236, w: 360, h: 574 } as const;
const KEPT = ['Centro de trabajo y área', 'Rango de antigüedad', 'Grupo de jefe'] as const;
const NOT_KEPT = ['Quién la contestó', 'Fecha ni hora', 'Llave a la invitación'] as const;

export const SurveyScene = ({ f, audioAt }: SceneProps) => {
    const c = useTheme();
    const pick = tween(f, audioAt + 50, dur.micro + 4);
    const sent = tween(f, audioAt + 92, 20, easeInOut);
    const rowAt = (i: number): number => audioAt + 70 + i * 10;
    return (
        <>
            <Phone style={{ left: PBOX.left, top: PBOX.top, width: PBOX.w, height: PBOX.h, ...enter(f, 2, { dur: dur.scene, x: -30, y: 0 }) }}>
                <div style={{ position: 'absolute', left: 22, right: 22, top: 62, opacity: swapOut(sent) }}>
                    <div style={{ fontSize: 26, color: c.mutedFg }}>Clima laboral</div>
                    <div style={{ marginTop: 10, height: 8, borderRadius: 99, background: c.track }}>
                        <div style={{ width: '38%', height: '100%', borderRadius: 99, background: c.primary }} />
                    </div>
                    <div style={{ marginTop: 22, fontSize: 30, fontWeight: 600, lineHeight: 1.22 }}>Me siento orgulloso(a) de trabajar en esta empresa.</div>
                    <div style={{ marginTop: 26, display: 'flex', justifyContent: 'space-between' }}>
                        {[1, 2, 3, 4, 5].map((n) => {
                            const on = n === 4 ? pick : 0;
                            return (
                                <div key={n} style={{ width: 50, height: 50, boxSizing: 'border-box', borderRadius: 99, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 26, fontWeight: 600, border: `2px solid ${on > 0.5 ? c.primary : c.border}`, background: `color-mix(in srgb, ${c.primary} ${on * 100}%, ${c.muted})`, color: on > 0.5 ? c.primaryFg : c.fg, ...enter(f, audioAt + 8 + n * 3, { dur: dur.enter, y: 8 }) }}>{n}</div>
                            );
                        })}
                    </div>
                    <div style={{ marginTop: 10, display: 'flex', justifyContent: 'space-between', fontSize: 24, lineHeight: 1.2, color: c.mutedFg }}>
                        <div style={{ width: 130 }}>Totalmente en desacuerdo</div>
                        <div style={{ width: 130, textAlign: 'right' }}>Totalmente de acuerdo</div>
                    </div>
                    <div style={{ marginTop: 26, fontSize: 26, color: c.mutedFg, ...pop(f, audioAt + 30) }}>Puede dejarla sin contestar.</div>
                </div>
                <div style={{ position: 'absolute', left: 26, right: 26, top: 140, opacity: swapIn(sent), textAlign: 'center' }}>
                    <CheckBadge size={96} style={{ margin: '0 auto', transform: `scale(${lerp(0.7, 1, sent)})` }} />
                    <div style={{ marginTop: 24, fontSize: 30, fontWeight: 600 }}>Gracias por contestar</div>
                    <div style={{ marginTop: 10, fontSize: 26, lineHeight: 1.3, color: c.mutedFg }}>Este enlace es de un solo uso.</div>
                </div>
            </Phone>
            <Card style={{ left: 780, top: 250, width: 1030, height: 540, ...enter(f, 8, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 34, fontSize: 36, fontWeight: 600 }}>Qué se guarda de cada respuesta</div>
                {[
                    { title: 'Sí se guarda', items: KEPT, yes: true, x: 44 },
                    { title: 'No se guarda', items: NOT_KEPT, yes: false, x: 536 },
                ].map((col, k) => (
                    <div key={col.title}>
                        <div style={{ position: 'absolute', left: col.x, top: 104, fontSize: 26, color: c.mutedFg }}>{col.title}</div>
                        {col.items.map((t, i) => {
                            const g = tween(f, rowAt(i) + k * 4, dur.enter, easeBack);
                            return (
                                <div key={t} style={{ position: 'absolute', left: col.x, top: 148 + i * 100, width: 450, height: 88, boxSizing: 'border-box', borderRadius: radius.control, background: c.muted, border: `1px solid ${col.yes && g > 0.5 ? c.primary : c.border}`, display: 'flex', alignItems: 'center', gap: 16, padding: '0 20px', opacity: lerp(0.3, 1, g), transform: `translateY(${(1 - g) * 12}px)` }}>
                                    {col.yes ? <CheckBadge size={40} /> : (
                                        <div style={{ width: 40, height: 40, borderRadius: 99, flexShrink: 0, display: 'flex', alignItems: 'center', justifyContent: 'center', background: c.track }}>
                                            <Icon name="eyeOff" size={24} color={c.mutedFg} />
                                        </div>
                                    )}
                                    <div style={{ fontSize: 28, fontWeight: 500, color: col.yes ? c.fg : c.mutedFg }}>{t}</div>
                                </div>
                            );
                        })}
                    </div>
                ))}
                <div style={{ position: 'absolute', left: 44, bottom: 36, ...pop(f, audioAt + 110) }}>
                    <Tag label="La invitación solo guarda «ya contestó»" />
                </div>
            </Card>
            {Array.from({ length: 6 }, (_, k) => (
                <FlyDot key={k} f={f} start={audioAt + 56 + k * 4} len={28} from={{ x: PBOX.left + 180, y: 620 }} to={{ x: 1000 + (k % 3) * 40, y: 400 + (k % 2) * 60 }} lift={60} size={14} />
            ))}
        </>
    );
};

// ───────────────────────── 2. Pulsos que dicen si funciona ─────────────────────────
// Pulso: hasta 11 preguntas (5 fijas + hasta 6 que rotan en una sola modalidad); mensual o trimestral; avisa 3 días antes.
const FIXED = ['Orgullo', 'Motivación', 'Conexión', 'Recomendación', 'Mejoras vistas'] as const;
const MODES = [
    { title: 'Seguimiento', text: 'Temas prioritarios del último clima' },
    { title: 'Recorrido', text: 'Un ancla por tema' },
    { title: 'Jefe', text: 'Dos preguntas sobre el jefe' },
] as const;

export const PulseScene = ({ f, audioAt }: SceneProps) => {
    const c = useTheme();
    const CLICK = audioAt + 56;
    const quarter = tween(f, CLICK + 2, dur.enter, easeBack);
    const mode = tween(f, audioAt + 118, dur.enter, easeBack);
    return (
        <>
            <Card style={{ left: 110, top: 250, width: 760, height: 540, ...enter(f, 2, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 34, fontSize: 36, fontWeight: 600 }}>Calendario del pulso</div>
                <div style={{ position: 'absolute', left: 44, right: 44, top: 104, display: 'flex', gap: 16 }}>
                    {['Mensual', 'Trimestral'].map((o, i) => {
                        const sel = i === 1 ? quarter > 0.5 : quarter <= 0.5;
                        return (
                            <div key={o} style={{ flex: 1, height: 92, boxSizing: 'border-box', borderRadius: radius.control, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 32, fontWeight: 600, border: `2px solid ${sel ? c.primary : c.border}`, background: sel ? c.accent : c.muted, ...enter(f, audioAt + 6 + i * stagger, { dur: dur.enter, y: 8 }) }}>
                                {o}
                            </div>
                        );
                    })}
                </div>
                {[
                    { icon: 'mail', text: 'Se lanza solo, en la fecha del calendario', at: audioAt + 74, top: 234 },
                    { icon: 'bell', text: 'Tres días antes te avisa, con los compromisos abiertos', at: audioAt + 92, top: 346 },
                ].map((r) => {
                    const g = tween(f, r.at, dur.enter, easeBack);
                    return (
                        <div key={r.text} style={{ position: 'absolute', left: 44, right: 44, top: r.top, height: 96, boxSizing: 'border-box', borderRadius: radius.control, background: c.muted, border: `1px solid ${c.border}`, display: 'flex', alignItems: 'center', gap: 18, padding: '0 22px', opacity: g, transform: `translateY(${(1 - g) * 12}px)` }}>
                            <Icon name={r.icon === 'mail' ? 'mail' : 'bell'} size={36} color={c.primary} />
                            <div style={{ fontSize: 27, fontWeight: 500, lineHeight: 1.2 }}>{r.text}</div>
                        </div>
                    );
                })}
            </Card>
            <Card style={{ left: 910, top: 250, width: 900, height: 540, ...enter(f, 8, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 34, fontSize: 36, fontWeight: 600 }}>Qué lleva un pulso</div>
                <div style={{ position: 'absolute', left: 44, top: 92 }}>
                    <Label>5 preguntas fijas</Label>
                </div>
                <div style={{ position: 'absolute', left: 44, right: 44, top: 136, display: 'flex', flexWrap: 'wrap', gap: 12 }}>
                    {FIXED.map((t, i) => (
                        <div key={t} style={pop(f, audioAt + 14 + i * 5)}>
                            <Tag label={t} />
                        </div>
                    ))}
                </div>
                <div style={{ position: 'absolute', left: 44, top: 262 }}>
                    <Label>Hasta 6 que rotan, en una sola modalidad</Label>
                </div>
                {MODES.map((m, i) => {
                    const on = i === 0 ? mode : 0;
                    return (
                        <div key={m.title} style={{ position: 'absolute', left: 44 + i * 274, top: 312, width: 262, height: 168, boxSizing: 'border-box', borderRadius: radius.control, padding: '18px 18px', border: `${on > 0.5 ? 2 : 1}px solid ${on > 0.5 ? c.primary : c.border}`, background: `color-mix(in srgb, ${c.accent} ${on * 100}%, ${c.muted})`, ...enter(f, audioAt + 40 + i * stagger, { dur: dur.enter, y: 10 }) }}>
                            <div style={{ fontSize: 30, fontWeight: 600 }}>{m.title}</div>
                            <div style={{ marginTop: 8, fontSize: 26, lineHeight: 1.25, color: c.mutedFg }}>{m.text}</div>
                        </div>
                    );
                })}
            </Card>
            <Cursor f={f} path={[{ at: audioAt + 28, x: 640, y: 480 }, { at: CLICK - 2, x: 700, y: 360 }]} clicks={[CLICK]} />
        </>
    );
};

// ───────────────────────── 3. Grupos pequeños, ocultos ─────────────────────────
// Qué atender primero (los temas con menor % favorable) y resultados por área; con menos de 5 respuestas el grupo se oculta
// y nunca se muestra su conteo exacto (README del módulo).
const DRIVERS = [
    { name: 'Reconocimiento', pct: 48 },
    { name: 'Comunicación', pct: 55 },
    { name: 'Crecimiento', pct: 61 },
] as const;
const AREAS = [
    { name: 'Producción', pct: 72 },
    { name: 'Administración', pct: 66 },
    { name: 'Almacén', pct: 0 },
] as const;

export const HiddenScene = ({ f, audioAt }: SceneProps) => {
    const c = useTheme();
    const fill = (i: number): number => tween(f, audioAt + 16 + i * 9, 34, easeInOut);
    const afill = (i: number): number => tween(f, audioAt + 70 + i * 9, 34, easeInOut);
    const lockAt = audioAt + 128;
    const hidden = tween(f, lockAt, dur.morph, easeInOut);
    return (
        <>
            <SampleTag f={f} at={audioAt} />
            <Card style={{ left: 110, top: 250, width: 760, height: 540, ...enter(f, 2, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 34, fontSize: 36, fontWeight: 600 }}>Qué atender primero</div>
                <div style={{ position: 'absolute', left: 44, top: 88 }}>
                    <Label>% favorable, de menor a mayor</Label>
                </div>
                {DRIVERS.map((d, i) => (
                    <div key={d.name} style={{ position: 'absolute', left: 44, right: 44, top: 146 + i * 126, height: 114, boxSizing: 'border-box', borderRadius: radius.control, background: c.muted, border: `${i === 0 ? 2 : 1}px solid ${i === 0 ? c.primary : c.border}`, padding: '16px 22px', ...enter(f, audioAt + 8 + i * stagger, { dur: dur.enter, y: 10 }) }}>
                        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                            <div style={{ fontSize: 30, fontWeight: 600 }}>{d.name}</div>
                            <div style={{ fontSize: 34, fontWeight: 600, color: i === 0 ? c.primary : c.fg }}>{`${Math.round(d.pct * fill(i))} %`}</div>
                        </div>
                        <div style={{ marginTop: 14, height: 12, borderRadius: 99, background: c.track }}>
                            <div style={{ height: '100%', borderRadius: 99, background: i === 0 ? c.primary : c.mutedFg, width: `${d.pct * fill(i)}%` }} />
                        </div>
                    </div>
                ))}
            </Card>
            <Card style={{ left: 910, top: 250, width: 900, height: 540, ...enter(f, 8, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 34, fontSize: 36, fontWeight: 600 }}>Resultados por área</div>
                {AREAS.map((a, i) => {
                    const isHidden = i === 2;
                    const n = Math.round((isHidden ? 40 : a.pct) * afill(i));
                    return (
                        <div key={a.name} style={{ position: 'absolute', left: 44, right: 44, top: 108 + i * 108, height: 96, boxSizing: 'border-box', borderRadius: radius.control, background: c.muted, border: `1px solid ${c.border}`, ...enter(f, audioAt + 60 + i * stagger, { dur: dur.enter, y: 10 }) }}>
                            <div style={{ position: 'absolute', left: 24, top: 28, fontSize: 30, fontWeight: 600 }}>{a.name}</div>
                            <div style={{ position: 'absolute', left: 290, top: 42, width: 400, height: 12, borderRadius: 99, background: c.track, opacity: isHidden ? swapOut(hidden) : 1 }}>
                                <div style={{ height: '100%', borderRadius: 99, background: c.primary, width: `${(isHidden ? 40 : a.pct) * afill(i)}%` }} />
                            </div>
                            {!isHidden ? <div style={{ position: 'absolute', right: 24, top: 26, fontSize: 36, fontWeight: 600 }}>{`${n} %`}</div> : (
                                <div style={{ position: 'absolute', left: 290, top: 28, display: 'flex', alignItems: 'center', gap: 14, fontSize: 28, color: c.mutedFg, opacity: swapIn(hidden) }}>
                                    <Icon name="lock" size={32} color={c.primary} /> Oculto: menos de 5 respuestas
                                </div>
                            )}
                        </div>
                    );
                })}
                <div style={{ position: 'absolute', left: 44, top: 434, ...pop(f, lockAt + 14) }}>
                    <Tag label="Mínimo por grupo: 5" />
                </div>
                <div style={{ position: 'absolute', left: 44, top: 486, fontSize: 26, color: c.mutedFg, opacity: tween(f, lockAt + 22, dur.enter) }}>Nunca se muestra cuántas personas son.</div>
            </Card>
        </>
    );
};

// ───────────────────────── 4. De una ola a la siguiente ─────────────────────────
// Una ola contra la anterior y un centro contra otro, con % favorable; las flechas de tendencia solo con 30 respuestas o más.
const BARMAX = 520;

export const CompareScene = ({ f, audioAt }: SceneProps) => {
    const c = useTheme();
    const CLICK = audioAt + 70;
    const press = tween(f, CLICK - 3, 6) * (1 - tween(f, CLICK + 3, 6));
    const g1 = tween(f, audioAt + 20, 36, easeInOut);
    const g2 = tween(f, CLICK + 6, 36, easeInOut);
    const g3 = tween(f, CLICK + 30, 36, easeInOut);
    const delta = tween(f, CLICK + 46, dur.enter, easeBack);
    const Bar = ({ y, name, pct, g, hot }: { y: number; name: string; pct: number; g: number; hot: boolean }) => (
        <div style={{ position: 'absolute', left: 0, right: 0, top: y, height: 96 }}>
            <div style={{ position: 'absolute', left: 0, top: 0, fontSize: 28, fontWeight: 600 }}>{name}</div>
            <div style={{ position: 'absolute', left: 0, top: 48, width: BARMAX, height: 36, borderRadius: 8, background: c.track }}>
                <div style={{ height: '100%', borderRadius: 8, width: BARMAX * (pct / 100) * g, background: hot ? c.primary : c.mutedFg }} />
            </div>
            <div style={{ position: 'absolute', left: BARMAX + 24, top: 44, fontSize: 38, fontWeight: 600, opacity: g }}>{`${Math.round(pct * g)} %`}</div>
        </div>
    );
    return (
        <>
            <SampleTag f={f} at={audioAt} />
            <Card style={{ left: 110, top: 250, width: 820, height: 540, ...enter(f, 2, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 34, fontSize: 36, fontWeight: 600 }}>Una ola con la anterior</div>
                <div style={{ position: 'absolute', left: 44, top: 88 }}>
                    <Label>Compromiso, % favorable</Label>
                </div>
                <div style={{ position: 'absolute', left: 44, right: 44, top: 140 }}>
                    <Bar y={0} name="Ola 2" pct={68} g={g1} hot />
                    <div style={{ opacity: tween(f, CLICK + 6, dur.enter) }}>
                        <Bar y={112} name="Ola 1" pct={61} g={g2} hot={false} />
                    </div>
                </div>
                <div style={{ position: 'absolute', right: 44, top: 26, display: 'flex', alignItems: 'center', gap: 8, height: 52, padding: '0 18px', borderRadius: radius.control, background: c.accent, border: `1px solid ${c.primary}`, fontSize: 30, fontWeight: 600, color: c.primary, opacity: delta, transform: `scale(${lerp(0.7, 1, delta)})` }}>
                    <Icon name="arrowUp" size={30} color={c.primary} stroke={3} /> +7 puntos
                </div>
                <Button label="Comparar con la anterior" pressed={press} style={{ position: 'absolute', left: 44, bottom: 40, ...pop(f, audioAt + 36) }} />
            </Card>
            <Card style={{ left: 970, top: 250, width: 840, height: 540, ...enter(f, 8, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 34, fontSize: 36, fontWeight: 600 }}>Un centro con otro</div>
                <div style={{ position: 'absolute', left: 44, top: 88 }}>
                    <Label>Compromiso, % favorable</Label>
                </div>
                <div style={{ position: 'absolute', left: 44, right: 44, top: 140, opacity: tween(f, CLICK + 24, dur.enter) }}>
                    <Bar y={0} name="Oficinas Monterrey" pct={72} g={g3} hot />
                    <Bar y={112} name="Planta Querétaro" pct={65} g={g3} hot={false} />
                </div>
                <div style={{ position: 'absolute', left: 44, bottom: 40, display: 'flex', alignItems: 'center', gap: 12, fontSize: 26, color: c.mutedFg, ...pop(f, CLICK + 70) }}>
                    <Icon name="lock" size={30} color={c.primary} /> Sin ninguna respuesta individual
                </div>
            </Card>
            {Array.from({ length: 6 }, (_, k) => (
                <FlyDot key={k} f={f} start={CLICK + 20 + k * 4} len={30} from={{ x: 560, y: 740 }} to={{ x: 1200 + k * 30, y: 340 }} lift={90} size={14} />
            ))}
            <Cursor f={f} path={[{ at: audioAt + 44, x: 760, y: 600 }, { at: CLICK - 2, x: 330, y: 738 }]} clicks={[CLICK]} />
        </>
    );
};
