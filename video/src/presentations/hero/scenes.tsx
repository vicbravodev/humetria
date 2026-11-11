import { easeInOut, enter, lerp, tween } from '../../ease';
import { Cursor, FlyDot, easeBack, pop, swapIn, swapOut } from '../../kit/fx';
import type { SceneProps } from '../../kit/schema';
import { useTheme } from '../../kit/themes';
import { dur, radius, stagger } from '../../kit/tokens';
import { Button, Card, CheckBadge, Icon, Meter, Phone, Tag } from '../../kit/ui';

/** Las cifras de los capítulos 4 y 5 son de demostración; se rotulan arriba a la derecha. */
const SampleTag = ({ f, at }: { f: number; at: number }) => (
    <div style={{ position: 'absolute', right: 110, top: 142, ...pop(f, at) }}>
        <Tag label="Cifras de ejemplo" />
    </div>
);

const Title = ({ children }: { children: string }) => <div style={{ position: 'absolute', left: 44, top: 34, fontSize: 36, fontWeight: 600 }}>{children}</div>;

// ───────────────────────── 1. Carga a tu gente una vez ─────────────────────────
const PEOPLE = [
    { initials: 'ML', name: 'Mariana López', area: 'Producción', center: 'Planta Querétaro' },
    { initials: 'JR', name: 'Jorge Ramírez', area: 'Administración', center: 'Oficinas Monterrey' },
    { initials: 'LH', name: 'Lucía Hernández', area: 'Almacén', center: 'Planta Querétaro' },
    { initials: 'AT', name: 'Andrés Torres', area: 'Producción', center: 'Planta Querétaro' },
] as const;
const STATS = [
    { n: 3, label: 'centros de trabajo' },
    { n: 8, label: 'áreas' },
    { n: 120, label: 'personas' },
] as const;

export const PeopleScene = ({ f, audioAt }: SceneProps) => {
    const c = useTheme();
    const count = Math.round(120 * tween(f, audioAt + 40, 70, easeInOut));
    const rowAt = (i: number): number => audioAt + 34 + i * 12;
    return (
        <>
            <Card style={{ left: 110, top: 250, width: 1010, height: 540, ...enter(f, 2, { dur: dur.scene, y: 18 }) }}>
                <Title>Plantilla</Title>
                <div style={{ position: 'absolute', left: 250, top: 26, height: 52, boxSizing: 'border-box', padding: '0 18px', display: 'flex', alignItems: 'center', gap: 12, borderRadius: radius.control, background: c.accent, border: `1px solid ${c.border}`, fontSize: 26, fontWeight: 500, ...pop(f, audioAt + 6) }}>
                    <Icon name="file" size={30} color={c.primary} /> empleados.csv
                </div>
                <div style={{ position: 'absolute', right: 44, top: 40, fontSize: 30, color: c.mutedFg }}>{`${count} personas`}</div>
                {PEOPLE.map((p, i) => (
                    <div key={p.name} style={{ position: 'absolute', left: 44, right: 44, top: 104 + i * 84, height: 76, boxSizing: 'border-box', borderRadius: radius.control, background: c.muted, border: `1px solid ${c.border}`, ...enter(f, rowAt(i), { dur: dur.enter, y: 12 }) }}>
                        <div style={{ position: 'absolute', left: 18, top: 15, width: 44, height: 44, borderRadius: 99, background: c.accent, color: c.primary, fontSize: 22, fontWeight: 600, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>{p.initials}</div>
                        <div style={{ position: 'absolute', left: 82, top: 20, fontSize: 28, fontWeight: 600 }}>{p.name}</div>
                        <div style={{ position: 'absolute', left: 340, top: 22, fontSize: 26, color: c.mutedFg }}>{p.area}</div>
                        <div style={{ position: 'absolute', left: 580, top: 16 }}>
                            <Tag label={p.center} />
                        </div>
                    </div>
                ))}
                <div style={{ position: 'absolute', left: 44, top: 452, fontSize: 26, color: c.mutedFg, opacity: tween(f, rowAt(3) + 14, dur.enter) }}>y 116 personas más</div>
            </Card>
            <Card style={{ left: 1160, top: 250, width: 650, height: 540, ...enter(f, 8, { dur: dur.scene, y: 18 }) }}>
                <Title>Tu organización</Title>
                {STATS.map((s, i) => {
                    const g = tween(f, audioAt + 50 + i * 14, 48, easeInOut);
                    return (
                        <div key={s.label} style={{ position: 'absolute', left: 44, right: 44, top: 108 + i * 128, height: 112, boxSizing: 'border-box', borderRadius: radius.control, background: c.muted, border: `1px solid ${c.border}`, display: 'flex', alignItems: 'center', gap: 24, padding: '0 26px', ...enter(f, audioAt + 44 + i * stagger, { dur: dur.enter, y: 10 }) }}>
                            <div style={{ width: 130, fontSize: 56, fontWeight: 600, color: c.primary }}>{Math.round(s.n * g)}</div>
                            <div style={{ fontSize: 30, fontWeight: 500 }}>{s.label}</div>
                        </div>
                    );
                })}
            </Card>
            {Array.from({ length: 8 }, (_, k) => (
                <FlyDot key={k} f={f} start={audioAt + 24 + k * 5} len={30} from={{ x: 560, y: 290 }} to={{ x: 700 + (k % 4) * 60, y: 400 + (k % 4) * 60 }} lift={50} size={14} />
            ))}
        </>
    );
};

// ───────────────────────── 2. Elige qué aplicar ─────────────────────────
const TOOLS = [
    { name: 'NOM-035', hint: 'Riesgo psicosocial' },
    { name: 'NOM-037', hint: 'Teletrabajo' },
    { name: 'Ley Silla', hint: 'Asientos y descansos' },
    { name: 'Buzón de quejas', hint: 'Quejas y denuncias' },
    { name: 'Compensación', hint: 'Revisión salarial' },
    { name: 'Registro de jornada', hint: 'Reloj checador' },
    { name: 'Vacaciones y permisos', hint: 'Saldos y permisos' },
    { name: 'Organigrama', hint: 'Quién reporta a quién' },
    { name: 'Clima y pulso', hint: 'Clima laboral' },
] as const;
const TW = 548;
const TH = 150;
const TGAP = 28;
const tileAt = (i: number): { x: number; y: number } => ({ x: 110 + (i % 3) * (TW + TGAP), y: 250 + Math.floor(i / 3) * (TH + TGAP) });

export const ToolsScene = ({ f, audioAt }: SceneProps) => {
    const c = useTheme();
    const picks: readonly { i: number; at: number }[] = [
        { i: 1, at: audioAt + 112 },
        { i: 3, at: audioAt + 152 },
        { i: 5, at: audioAt + 180 },
        { i: 6, at: audioAt + 210 },
        { i: 7, at: audioAt + 238 },
        { i: 8, at: audioAt + 262 },
    ];
    const center = (i: number): { x: number; y: number } => ({ x: tileAt(i).x + 300, y: tileAt(i).y + 95 });
    const route = picks.flatMap((p) => [
        { at: p.at - 6, x: center(p.i).x, y: center(p.i).y },
        { at: p.at + 3, x: center(p.i).x, y: center(p.i).y },
    ]);
    return (
        <>
            {TOOLS.map((t, i) => {
                const pick = picks.find((p) => p.i === i);
                const on = pick ? tween(f, pick.at, dur.micro + 6, easeBack) : 0;
                const pos = tileAt(i);
                return (
                    <div key={t.name} style={{ position: 'absolute', left: pos.x, top: pos.y, width: TW, height: TH, boxSizing: 'border-box', borderRadius: radius.card, padding: '0 28px', display: 'flex', alignItems: 'center', gap: 16, border: `${on > 0.5 ? 2 : 1}px solid ${on > 0.5 ? c.primary : c.border}`, background: `color-mix(in srgb, ${c.accent} ${on * 100}%, ${c.card})`, ...enter(f, 4 + i * 4, { dur: dur.scene, y: 18 }) }}>
                        <div style={{ flex: 1 }}>
                            <div style={{ fontSize: 32, fontWeight: 600, lineHeight: 1.2 }}>{t.name}</div>
                            <div style={{ marginTop: 6, fontSize: 26, lineHeight: 1.2, color: c.mutedFg }}>{t.hint}</div>
                        </div>
                        {on > 0.01 ? <CheckBadge size={44} style={{ transform: `scale(${lerp(0.5, 1, on)})`, opacity: on }} /> : <div style={{ width: 44, height: 44, boxSizing: 'border-box', borderRadius: 99, border: `2px solid ${c.border}`, flexShrink: 0 }} />}
                    </div>
                );
            })}
            <Cursor f={f} path={[{ at: audioAt + 50, x: 960, y: 560 }, ...route]} clicks={picks.map((p) => p.at)} />
        </>
    );
};

// ───────────────────────── 3. Cada quien, desde su celular ─────────────────────────
const PB = { left: 300, top: 236, w: 360, h: 574 } as const;
const PROGRESS = [
    { name: 'Clima y pulso', total: 60, done: 48 },
    { name: 'Ley Silla', total: 36, done: 30 },
    { name: 'NOM-037', total: 24, done: 18 },
] as const;

export const InviteScene = ({ f, audioAt }: SceneProps) => {
    const c = useTheme();
    const pick = tween(f, audioAt + 40, dur.micro + 4);
    const sent = tween(f, audioAt + 76, 20, easeInOut);
    const fillStart = audioAt + 82;
    const total = tween(f, fillStart + 20, 90, easeInOut);
    return (
        <>
            <Phone style={{ left: PB.left, top: PB.top, width: PB.w, height: PB.h, ...enter(f, 2, { dur: dur.scene, x: -30, y: 0 }) }}>
                <div style={{ position: 'absolute', left: 22, right: 22, top: 62, opacity: swapOut(sent) }}>
                    <Tag label="Enlace de un solo uso" />
                    <div style={{ marginTop: 22, fontSize: 30, fontWeight: 600, lineHeight: 1.22 }}>En mi área hay buena comunicación.</div>
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
                </div>
                <div style={{ position: 'absolute', left: 26, right: 26, top: 140, opacity: swapIn(sent), textAlign: 'center' }}>
                    <CheckBadge size={96} style={{ margin: '0 auto', transform: `scale(${lerp(0.7, 1, sent)})` }} />
                    <div style={{ marginTop: 24, fontSize: 30, fontWeight: 600 }}>Gracias por contestar</div>
                </div>
            </Phone>
            <Card style={{ left: 780, top: 250, width: 1030, height: 540, ...enter(f, 8, { dur: dur.scene, y: 18 }) }}>
                <Title>Avance por herramienta</Title>
                <div style={{ position: 'absolute', right: 44, top: 40, fontSize: 30, fontWeight: 600, color: c.primary }}>{`${Math.round(96 * total)} de 120`}</div>
                <div style={{ position: 'absolute', left: 44, right: 44, top: 104, height: 20, borderRadius: 99, background: c.track }}>
                    <div style={{ height: '100%', borderRadius: 99, background: c.primary, width: `${80 * total}%` }} />
                </div>
                {PROGRESS.map((a, i) => {
                    const g = tween(f, fillStart + 30 + i * 8, 70, easeInOut);
                    return (
                        <div key={a.name} style={{ position: 'absolute', left: 44, right: 44, top: 152 + i * 76, height: 64, boxSizing: 'border-box', borderRadius: radius.control, background: c.muted, border: `1px solid ${c.border}`, ...enter(f, audioAt + 30 + i * stagger, { dur: dur.enter, y: 10 }) }}>
                            <div style={{ position: 'absolute', left: 20, top: 15, fontSize: 28, fontWeight: 600 }}>{a.name}</div>
                            <div style={{ position: 'absolute', left: 330, top: 26, width: 430, height: 12, borderRadius: 99, background: c.track }}>
                                <div style={{ height: '100%', borderRadius: 99, background: c.primary, width: `${(a.done / a.total) * 100 * g}%` }} />
                            </div>
                            <div style={{ position: 'absolute', right: 20, top: 16, fontSize: 28, fontWeight: 500, color: c.mutedFg }}>{`${Math.round(a.done * g)} de ${a.total}`}</div>
                        </div>
                    );
                })}
                <div style={{ position: 'absolute', left: 44, top: 404, ...pop(f, audioAt + 130) }}>
                    <Tag label="Ves quién ya contestó, no qué contestó" />
                </div>
                <div style={{ position: 'absolute', left: 44, top: 458, fontSize: 26, color: c.mutedFg, opacity: tween(f, audioAt + 140, dur.enter) }}>A quien falta, un recordatorio.</div>
            </Card>
            {Array.from({ length: 8 }, (_, k) => (
                <FlyDot key={k} f={f} start={fillStart + k * 6} len={30} from={{ x: PB.left + 180, y: 620 }} to={{ x: 1000 + k * 60, y: 376 }} lift={80} size={14} />
            ))}
        </>
    );
};

// ───────────────────────── 4. Resultados sin nombres ─────────────────────────
const RESULTS = [
    { name: 'Producción', pct: 72 },
    { name: 'Administración', pct: 66 },
    { name: 'Almacén', pct: 0 },
] as const;
const SEES = [
    { text: 'Porcentajes por grupo', yes: true },
    { text: 'Grupos de 5 personas o más', yes: true },
    { text: 'No ves nombres', yes: false },
    { text: 'No ves respuestas sueltas', yes: false },
] as const;

export const GroupsScene = ({ f, audioAt }: SceneProps) => {
    const c = useTheme();
    const lockAt = audioAt + 120;
    const hidden = tween(f, lockAt, dur.morph, easeInOut);
    return (
        <>
            <SampleTag f={f} at={audioAt} />
            <Card style={{ left: 110, top: 250, width: 900, height: 540, ...enter(f, 2, { dur: dur.scene, y: 18 }) }}>
                <Title>Resultados por área</Title>
                {RESULTS.map((r, i) => {
                    const isHidden = i === 2;
                    const g = tween(f, audioAt + 20 + i * 10, 50, easeInOut);
                    return (
                        <div key={r.name} style={{ position: 'absolute', left: 44 + i * 276, top: 140, width: 260, ...enter(f, audioAt + 10 + i * stagger, { dur: dur.enter, y: 12 }) }}>
                            <div style={{ position: 'relative', width: 200, height: 200, margin: '0 auto' }}>
                                <div style={{ position: 'absolute', inset: 0, opacity: isHidden ? swapOut(hidden) : 1 }}>
                                    <Meter value={(isHidden ? 40 : r.pct) * g} size={200}>
                                        <div style={{ fontSize: 40, fontWeight: 600 }}>{`${Math.round((isHidden ? 40 : r.pct) * g)} %`}</div>
                                    </Meter>
                                </div>
                                {isHidden ? (
                                    <div style={{ position: 'absolute', inset: 0, display: 'flex', alignItems: 'center', justifyContent: 'center', opacity: swapIn(hidden) }}>
                                        <Icon name="lock" size={72} color={c.primary} />
                                    </div>
                                ) : null}
                            </div>
                            <div style={{ marginTop: 26, textAlign: 'center', fontSize: 30, fontWeight: 600 }}>{r.name}</div>
                            {isHidden ? <div style={{ marginTop: 8, textAlign: 'center', fontSize: 26, color: c.mutedFg, opacity: swapIn(hidden) }}>Oculto</div> : null}
                        </div>
                    );
                })}
            </Card>
            <Card style={{ left: 1050, top: 250, width: 760, height: 540, ...enter(f, 8, { dur: dur.scene, y: 18 }) }}>
                <Title>Qué ve recursos humanos</Title>
                {SEES.map((s, i) => {
                    const g = tween(f, audioAt + 50 + i * 16, dur.enter, easeBack);
                    return (
                        <div key={s.text} style={{ position: 'absolute', left: 44, right: 44, top: 108 + i * 100, height: 88, boxSizing: 'border-box', borderRadius: radius.control, background: c.muted, border: `1px solid ${s.yes && g > 0.5 ? c.primary : c.border}`, display: 'flex', alignItems: 'center', gap: 18, padding: '0 22px', opacity: g, transform: `translateY(${(1 - g) * 12}px)` }}>
                            {s.yes ? <CheckBadge size={40} /> : (
                                <div style={{ width: 40, height: 40, borderRadius: 99, flexShrink: 0, display: 'flex', alignItems: 'center', justifyContent: 'center', background: c.track }}>
                                    <Icon name="eyeOff" size={24} color={c.mutedFg} />
                                </div>
                            )}
                            <div style={{ fontSize: 28, fontWeight: 500, color: s.yes ? c.fg : c.mutedFg }}>{s.text}</div>
                        </div>
                    );
                })}
            </Card>
        </>
    );
};

// ───────────────────────── 5. Todo en un expediente ─────────────────────────
const DOCS = ['Informe de riesgo psicosocial', 'Política de teletrabajo', 'Registro de jornada', 'Organigrama vigente'] as const;

export const DossierScene = ({ f, audioAt }: SceneProps) => {
    const c = useTheme();
    const tickAt = (i: number): number => audioAt + 66 + i * 32;
    const done = DOCS.filter((_, i) => f >= tickAt(i) + 6).length;
    const CLICK = audioAt + 196;
    const press = tween(f, CLICK - 3, 6) * (1 - tween(f, CLICK + 3, 6));
    const got = tween(f, CLICK + 4, dur.enter) > 0.5;
    const meter = tween(f, tickAt(0), 110, easeInOut);
    return (
        <>
            <Card style={{ left: 110, top: 250, width: 1010, height: 540, ...enter(f, 2, { dur: dur.scene, y: 18 }) }}>
                <Title>Expediente · Planta Querétaro</Title>
                {DOCS.map((d, i) => {
                    const t = tween(f, tickAt(i), dur.enter, easeBack);
                    return (
                        <div key={d} style={{ position: 'absolute', left: 44, right: 44, top: 112 + i * 96, height: 84, boxSizing: 'border-box', borderRadius: radius.control, background: c.muted, border: `1px solid ${t > 0.5 ? c.primary : c.border}`, display: 'flex', alignItems: 'center', gap: 18, padding: '0 22px', ...enter(f, audioAt + 14 + i * stagger, { dur: dur.enter, y: 10 }) }}>
                            <Icon name="file" size={36} color={t > 0.5 ? c.primary : c.mutedFg} />
                            <div style={{ flex: 1, fontSize: 29, fontWeight: 600 }}>{d}</div>
                            <div style={{ display: 'flex', alignItems: 'center', gap: 10, opacity: t, transform: `scale(${lerp(0.7, 1, t)})`, fontSize: 26, fontWeight: 600, color: c.primary }}>
                                <CheckBadge size={36} /> Listo
                            </div>
                        </div>
                    );
                })}
            </Card>
            <Card style={{ left: 1160, top: 250, width: 650, height: 540, ...enter(f, 8, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, right: 44, top: 44, display: 'flex', justifyContent: 'center' }}>
                    <Meter value={(done / DOCS.length) * 100 * Math.min(1, meter * 4)} size={260}>
                        <div style={{ fontSize: 56, fontWeight: 600 }}>{`${done}/${DOCS.length}`}</div>
                    </Meter>
                </div>
                <div style={{ position: 'absolute', left: 44, right: 44, top: 326, textAlign: 'center', fontSize: 28, color: c.mutedFg }}>documentos listos</div>
                <Button label={got ? 'Expediente descargado' : 'Descargar expediente'} pressed={press} style={{ position: 'absolute', left: 44, right: 44, bottom: 40, boxSizing: 'border-box', justifyContent: 'center', opacity: tween(f, audioAt + 150, dur.enter) }} />
            </Card>
            {DOCS.map((d, i) => (
                <FlyDot key={d} f={f} start={tickAt(i) + 4} len={28} from={{ x: 1060, y: 400 + i * 96 }} to={{ x: 1480, y: 450 }} lift={70} size={14} />
            ))}
            <Cursor f={f} path={[{ at: audioAt + 150, x: 1700, y: 640 }, { at: CLICK - 2, x: 1660, y: 724 }]} clicks={[CLICK]} />
        </>
    );
};
