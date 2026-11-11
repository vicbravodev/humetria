import type { CSSProperties } from 'react';
import { easeInOut, enter, lerp, tween } from '../../ease';
import { Cursor, FlyDot, easeBack, pop, type Pt, swapIn, swapOut } from '../../kit/fx';
import type { SceneProps } from '../../kit/schema';
import { useTheme } from '../../kit/themes';
import { dur, radius } from '../../kit/tokens';
import { Button, Card, CheckBadge, Icon, Phone, Tag } from '../../kit/ui';

const Label = ({ children }: { children: string }) => {
    const c = useTheme();
    return <div style={{ fontSize: 26, color: c.mutedFg }}>{children}</div>;
};

/** Tarjeta de persona del organigrama (centrada en `at`). `state`: normal, hot (resaltada) o dim (fuera de alcance). */
const OrgNode = ({ at, name, role, w = 236, h = 92, state = 'normal', style }: { at: Pt; name: string; role: string; w?: number; h?: number; state?: 'normal' | 'hot' | 'dim'; style?: CSSProperties }) => {
    const c = useTheme();
    const hot = state === 'hot';
    return (
        <div style={{ position: 'absolute', left: at.x - w / 2, top: at.y - h / 2, width: w, height: h, boxSizing: 'border-box', borderRadius: radius.control, background: hot ? c.accent : c.muted, border: `${hot ? 2 : 1}px solid ${hot ? c.primary : c.border}`, padding: '0 18px', display: 'flex', flexDirection: 'column', justifyContent: 'center', opacity: state === 'dim' ? 0.4 : 1, ...style }}>
            <div style={{ fontSize: 28, fontWeight: 600, whiteSpace: 'nowrap' }}>{name}</div>
            <div style={{ fontSize: 24, color: c.mutedFg, whiteSpace: 'nowrap' }}>{role}</div>
        </div>
    );
};

/** Conector en codo del borde inferior del padre al superior del hijo; `t` (0–1) lo dibuja en tres tramos. */
const Elbow = ({ from, to, t, h = 92, color }: { from: Pt; to: Pt; t: number; h?: number; color?: string | undefined }) => {
    const c = useTheme();
    const col = color ?? c.border;
    const y0 = from.y + h / 2;
    const y1 = to.y - h / 2;
    const mid = (y0 + y1) / 2;
    const a = Math.min(1, t * 3);
    const b = Math.max(0, Math.min(1, t * 3 - 1));
    const d = Math.max(0, Math.min(1, t * 3 - 2));
    const wd = Math.abs(to.x - from.x);
    const line: CSSProperties = { position: 'absolute', background: col, borderRadius: 2 };
    return (
        <>
            <div style={{ ...line, left: from.x - 1.5, top: y0, width: 3, height: (mid - y0) * a }} />
            <div style={{ ...line, left: to.x >= from.x ? from.x : from.x - wd * b, top: mid - 1.5, width: wd * b, height: 3 }} />
            <div style={{ ...line, left: to.x - 1.5, top: mid, width: 3, height: (y1 - mid) * d }} />
        </>
    );
};

// ───────────────────────── 1. Quién reporta a quién ─────────────────────────
// El organigrama sale de la línea de reporte (quién reporta a quién), sin límite de niveles.
const ROOT: Pt = { x: 960, y: 335 };
const L2: readonly { at: Pt; name: string; role: string }[] = [
    { at: { x: 460, y: 490 }, name: 'Carlos P.', role: 'Operaciones' },
    { at: { x: 960, y: 490 }, name: 'Elena V.', role: 'Administración' },
    { at: { x: 1460, y: 490 }, name: 'Laura M.', role: 'Ventas' },
];
const L3: readonly { at: Pt; name: string; role: string; parent: number }[] = [
    { at: { x: 310, y: 645 }, name: 'Jorge R.', role: 'Operador', parent: 0 },
    { at: { x: 610, y: 645 }, name: 'Lucía H.', role: 'Supervisora', parent: 0 },
    { at: { x: 960, y: 645 }, name: 'Pablo G.', role: 'Analista', parent: 1 },
    { at: { x: 1310, y: 645 }, name: 'Diana U.', role: 'Ejecutiva', parent: 2 },
    { at: { x: 1610, y: 645 }, name: 'Mariana L.', role: 'Ejecutiva', parent: 2 },
];

export const ChartScene = ({ f, audioAt }: SceneProps) => {
    const c = useTheme();
    const l1 = tween(f, audioAt + 12, 26, easeInOut);
    const l2 = tween(f, audioAt + 38, 24, easeInOut);
    return (
        <>
            <Card style={{ left: 110, top: 250, width: 1700, height: 540, ...enter(f, 2, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 32, fontSize: 36, fontWeight: 600 }}>Organigrama</div>
                <div style={{ position: 'absolute', left: 44, top: 80 }}>
                    <Label>Línea de reporte</Label>
                </div>
            </Card>
            {L2.map((n) => (
                <Elbow key={n.name} from={ROOT} to={n.at} t={l1} />
            ))}
            {L3.map((n) => (
                <Elbow key={n.name} from={L2[n.parent]?.at ?? ROOT} to={n.at} t={l2} />
            ))}
            <OrgNode at={ROOT} name="Sofía M." role="Dirección General" state="hot" style={pop(f, audioAt + 4)} />
            {L2.map((n, i) => (
                <OrgNode key={n.name} at={n.at} name={n.name} role={n.role} style={pop(f, audioAt + 16 + i * 5)} />
            ))}
            {L3.map((n, i) => (
                <OrgNode key={n.name} at={n.at} name={n.name} role={n.role} style={pop(f, audioAt + 44 + i * 5)} />
            ))}
            <div style={{ position: 'absolute', left: 1500, top: 270, ...pop(f, audioAt + 96) }}>
                <Tag label="9 personas · 3 niveles" />
            </div>
            {L3.map((n, k) => (
                <FlyDot key={n.name} f={f} start={audioAt + 70 + k * 5} len={32} from={{ x: ROOT.x, y: ROOT.y + 46 }} to={{ x: n.at.x, y: n.at.y - 46 }} lift={20 + k * 4} size={14} color={c.primary} />
            ))}
        </>
    );
};

// ───────────────────────── 2. Cárgalo desde tu archivo ─────────────────────────
// El archivo de personal lleva la columna `jefe` (número de empleado o, si no tiene, correo).
const ROWS = [
    { name: 'Mariana L.', num: '1044', boss: '1021' },
    { name: 'Diana U.', num: '1038', boss: '1021' },
    { name: 'Jorge R.', num: '1052', boss: '1015' },
    { name: 'Lucía H.', num: '1057', boss: '1015' },
    { name: 'Laura M.', num: '1021', boss: '1001' },
] as const;
const FROW = (i: number): number => 250 + 176 + i * 64;
const LINES = [
    ['Mariana L.', 'Laura M.'],
    ['Jorge R.', 'Carlos P.'],
    ['Laura M.', 'Sofía M.'],
] as const;

export const ImportScene = ({ f, audioAt }: SceneProps) => {
    const c = useTheme();
    const CLICK = audioAt + 62;
    const press = tween(f, CLICK - 3, 6) * (1 - tween(f, CLICK + 3, 6));
    const read = (i: number): number => tween(f, CLICK + 6 + i * 5, dur.micro + 4);
    const count = (to: number, at: number): number => Math.round(to * tween(f, at, 26, (t) => t));
    return (
        <>
            <Card style={{ left: 110, top: 250, width: 980, height: 540, ...enter(f, 2, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 32, display: 'flex', alignItems: 'center', gap: 14, fontSize: 36, fontWeight: 600 }}>
                    <Icon name="file" size={40} color={c.primary} /> personal.csv
                </div>
                <div style={{ position: 'absolute', left: 44, right: 44, top: 108, height: 44, display: 'flex', alignItems: 'center', fontSize: 26, color: c.mutedFg }}>
                    <div style={{ width: 340 }}>nombre</div>
                    <div style={{ width: 300 }}>numero_de_empleado</div>
                    <div style={{ color: c.primary, fontWeight: 600 }}>jefe</div>
                </div>
                <Button label="Importar" pressed={press} style={{ position: 'absolute', right: 44, top: 22, ...pop(f, audioAt + 24) }} />
            </Card>
            {ROWS.map((r, i) => (
                <div key={r.name} style={{ position: 'absolute', left: 154, top: FROW(i), width: 892, height: 56, boxSizing: 'border-box', borderRadius: radius.control, background: c.muted, border: `1px solid ${read(i) > 0.5 ? c.primary : c.border}`, display: 'flex', alignItems: 'center', padding: '0 20px', fontSize: 28, ...enter(f, audioAt + 4 + i * 4, { dur: dur.enter, y: 8 }) }}>
                    <div style={{ width: 340, fontWeight: 600 }}>{r.name}</div>
                    <div style={{ width: 300, color: c.mutedFg }}>{r.num}</div>
                    <div style={{ width: 150, fontWeight: 600, color: c.primary }}>{r.boss}</div>
                    <div style={{ marginLeft: 'auto', display: 'flex', opacity: read(i) }}>
                        <Icon name="check" size={28} color={c.primary} stroke={3} />
                    </div>
                </div>
            ))}
            <Card style={{ left: 1130, top: 250, width: 680, height: 540, ...enter(f, 8, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 32, fontSize: 36, fontWeight: 600 }}>Resultado de la carga</div>
                {[
                    ['Personas', count(5, CLICK + 10)],
                    ['Con jefe', count(5, CLICK + 18)],
                    ['Sin jefe', 0],
                ].map(([k, v], i) => (
                    <div key={String(k)} style={{ position: 'absolute', left: 44 + i * 204, top: 104, width: 188, height: 112, boxSizing: 'border-box', borderRadius: radius.control, background: c.muted, border: `1px solid ${c.border}`, padding: '14px 18px', opacity: tween(f, CLICK + 8 + i * 6, dur.enter) }}>
                        <div style={{ fontSize: 54, fontWeight: 600, lineHeight: 1, color: i < 2 ? c.primary : c.fg }}>{String(v)}</div>
                        <div style={{ marginTop: 6, fontSize: 24, color: c.mutedFg }}>{String(k)}</div>
                    </div>
                ))}
                {LINES.map(([a, b], i) => (
                    <div key={a} style={{ position: 'absolute', left: 44, right: 44, top: 244 + i * 62, height: 52, display: 'flex', alignItems: 'center', gap: 14, fontSize: 28, opacity: tween(f, CLICK + 40 + i * 10, dur.enter), transform: `translateX(${(1 - tween(f, CLICK + 40 + i * 10, dur.enter)) * 18}px)` }}>
                        <span style={{ fontWeight: 600 }}>{a}</span>
                        <Icon name="arrowRight" size={28} color={c.primary} />
                        <span style={{ color: c.mutedFg }}>{b}</span>
                    </div>
                ))}
                <div style={{ position: 'absolute', left: 44, right: 44, bottom: 32, fontSize: 24, lineHeight: 1.3, color: c.mutedFg, opacity: tween(f, CLICK + 70, dur.enter) }}>Jefe: su número de empleado o, si no tiene, su correo.</div>
            </Card>
            {ROWS.map((r, i) => (
                <FlyDot key={r.name} f={f} start={CLICK + 8 + i * 4} len={28} from={{ x: 1046, y: FROW(i) + 28 }} to={{ x: 1300 + i * 40, y: 330 }} lift={50} size={14} />
            ))}
            <Cursor f={f} path={[{ at: audioAt + 34, x: 700, y: 560 }, { at: CLICK - 2, x: 960, y: 306 }]} clicks={[CLICK]} />
        </>
    );
};

// ───────────────────────── 3. Sin equipos huérfanos ─────────────────────────
// Al dar de baja a un jefe se pide a quién reasignar su equipo; mientras tanto, su gente aparece con «Jefe dado de baja».
const SOFIA: Pt = { x: 1330, y: 345 };
const CARLOS: Pt = { x: 1100, y: 490 };
const ELENA: Pt = { x: 1560, y: 490 };
const JORGE0: Pt = { x: 950, y: 650 };
const LUCIA0: Pt = { x: 1250, y: 650 };
const JORGE1: Pt = { x: 1330, y: 490 };
const NW = 210;
const NH = 84;

export const LeaveScene = ({ f, audioAt }: SceneProps) => {
    const c = useTheme();
    const CLICK = audioAt + 60;
    const press = tween(f, CLICK - 3, 6) * (1 - tween(f, CLICK + 3, 6));
    const gone = tween(f, CLICK + 4, dur.enter);
    const warn = tween(f, CLICK + 4, dur.enter) * (1 - tween(f, CLICK + 40, dur.enter));
    const move = tween(f, CLICK + 34, 34, easeInOut);
    const done = tween(f, CLICK + 70, dur.enter, easeBack);
    const pos = (a: Pt, b: Pt): Pt => ({ x: lerp(a.x, b.x, move), y: lerp(a.y, b.y, move) });
    const jorge = pos(JORGE0, JORGE1);
    const lucia = pos(LUCIA0, { x: 1100, y: 490 });
    return (
        <>
            <Card style={{ left: 110, top: 250, width: 700, height: 540, ...enter(f, 2, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 32, fontSize: 36, fontWeight: 600 }}>Dar de baja a Carlos P.</div>
                <div style={{ position: 'absolute', left: 44, top: 90 }}>
                    <Label>Tiene 2 personas a su cargo</Label>
                </div>
                <div style={{ position: 'absolute', left: 44, top: 160, fontSize: 26, color: c.mutedFg }}>Reasignar su equipo a</div>
                <div style={{ position: 'absolute', left: 44, right: 44, top: 204, height: 76, boxSizing: 'border-box', borderRadius: radius.control, background: c.muted, border: `1px solid ${c.primary}`, display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '0 24px', fontSize: 30, fontWeight: 600, ...enter(f, audioAt + 20, { dur: dur.enter, y: 8 }) }}>
                    <span>Sofía M. · Dirección General</span>
                </div>
                <div style={{ position: 'absolute', left: 44, bottom: 40, opacity: swapOut(done) }}>
                    <Button label="Dar de baja" pressed={press} />
                </div>
                <div style={{ position: 'absolute', left: 44, bottom: 40, display: 'flex', alignItems: 'center', gap: 14, height: 64, padding: '0 22px', borderRadius: radius.control, background: c.accent, border: `1px solid ${c.primary}`, fontSize: 28, fontWeight: 600, opacity: swapIn(done), transform: `scale(${lerp(0.9, 1, done)})`, transformOrigin: 'left center' }}>
                    <CheckBadge size={34} /> Equipo reasignado
                </div>
            </Card>
            <Card style={{ left: 850, top: 250, width: 960, height: 540, ...enter(f, 8, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 32, fontSize: 36, fontWeight: 600 }}>Organigrama</div>
            </Card>
            <div style={{ opacity: 1 - move }}>
                <Elbow from={CARLOS} to={JORGE0} t={1} h={NH} />
                <Elbow from={CARLOS} to={LUCIA0} t={1} h={NH} />
            </div>
            <Elbow from={SOFIA} to={CARLOS} t={1} h={NH} />
            <Elbow from={SOFIA} to={ELENA} t={1} h={NH} />
            <div style={{ opacity: move }}>
                <Elbow from={SOFIA} to={JORGE1} t={1} h={NH} color={c.primary} />
            </div>
            <OrgNode at={SOFIA} name="Sofía M." role="Dirección" w={NW} h={NH} state="hot" style={enter(f, audioAt + 6, { dur: dur.enter, y: 8 })} />
            <OrgNode at={ELENA} name="Elena V." role="Administración" w={NW} h={NH} style={enter(f, audioAt + 10, { dur: dur.enter, y: 8 })} />
            <OrgNode at={CARLOS} name="Carlos P." role="Operaciones" w={NW} h={NH} style={{ ...enter(f, audioAt + 8, { dur: dur.enter, y: 8 }), opacity: lerp(1, 0, tween(f, CLICK + 40, dur.enter)) * tween(f, audioAt + 8, dur.enter) }} />
            <OrgNode at={jorge} name="Jorge R." role="Operador" w={NW} h={NH} style={enter(f, audioAt + 14, { dur: dur.enter, y: 8 })} />
            <OrgNode at={lucia} name="Lucía H." role="Supervisora" w={NW} h={NH} style={enter(f, audioAt + 18, { dur: dur.enter, y: 8 })} />
            <div style={{ position: 'absolute', left: CARLOS.x + NW / 2 + 14, top: CARLOS.y - 18, height: 36, padding: '0 14px', display: 'flex', alignItems: 'center', borderRadius: radius.control, background: c.track, fontSize: 24, fontWeight: 600, opacity: gone * (1 - tween(f, CLICK + 36, dur.enter)) }}>Dado de baja</div>
            {[JORGE0, LUCIA0].map((p, i) => (
                <div key={i} style={{ position: 'absolute', left: lerp(p.x, i === 0 ? JORGE1.x : 1100, move) - 100, top: lerp(p.y, i === 0 ? JORGE1.y : 490, move) + 48, height: 36, padding: '0 14px', display: 'flex', alignItems: 'center', borderRadius: radius.control, background: c.warnBg, color: c.warnFg, fontSize: 24, fontWeight: 600, opacity: warn }}>Jefe dado de baja</div>
            ))}
            {[0, 1].map((k) => (
                <FlyDot key={k} f={f} start={CLICK + 28 + k * 5} len={30} from={{ x: CARLOS.x, y: CARLOS.y + 40 }} to={{ x: SOFIA.x, y: SOFIA.y + 42 }} lift={80} size={16} />
            ))}
            <Cursor f={f} path={[{ at: audioAt + 30, x: 700, y: 740 }, { at: CLICK - 2, x: 230, y: 730 }]} clicks={[CLICK]} />
        </>
    );
};

// ───────────────────────── 4. Cada jefe ve a su gente ─────────────────────────
// En «Mi equipo», cada jefe ve solo a quienes le reportan y aprueba sus solicitudes.
const T_ROOT: Pt = { x: 1295, y: 345 };
const T_L2: readonly { at: Pt; name: string; role: string; mine: boolean }[] = [
    { at: { x: 1030, y: 490 }, name: 'Carlos P.', role: 'Operaciones', mine: false },
    { at: { x: 1295, y: 490 }, name: 'Elena V.', role: 'Administración', mine: false },
    { at: { x: 1560, y: 490 }, name: 'Laura M.', role: 'Ventas', mine: true },
];
const T_L3: readonly { at: Pt; name: string; role: string; mine: boolean; parent: number }[] = [
    { at: { x: 910, y: 640 }, name: 'Jorge R.', role: 'Operador', mine: false, parent: 0 },
    { at: { x: 1150, y: 640 }, name: 'Lucía H.', role: 'Supervisora', mine: false, parent: 0 },
    { at: { x: 1450, y: 640 }, name: 'Diana U.', role: 'Ejecutiva', mine: true, parent: 2 },
    { at: { x: 1670, y: 640 }, name: 'Mariana L.', role: 'Ejecutiva', mine: true, parent: 2 },
];
const PBOX = { left: 300, top: 236, w: 360, h: 574 } as const;

export const PortalScene = ({ f, audioAt }: SceneProps) => {
    const c = useTheme();
    const CLICK = audioAt + 100;
    const press = tween(f, CLICK - 3, 6) * (1 - tween(f, CLICK + 3, 6));
    const ok = tween(f, CLICK + 6, dur.morph, easeBack);
    const focus = tween(f, audioAt + 52, dur.morph, easeInOut);
    const NW2 = 200;
    const NH2 = 84;
    const stateOf = (mine: boolean): 'hot' | 'dim' | 'normal' => (mine ? 'hot' : focus > 0.5 ? 'dim' : 'normal');
    return (
        <>
            <Phone style={{ left: PBOX.left, top: PBOX.top, width: PBOX.w, height: PBOX.h, ...enter(f, 2, { dur: dur.scene, x: -30, y: 0 }) }}>
                <div style={{ position: 'absolute', left: 22, right: 22, top: 58 }}>
                    <div style={{ fontSize: 30, fontWeight: 600 }}>Mi equipo</div>
                    <div style={{ marginTop: 8, fontSize: 24, color: c.mutedFg }}>Su jefe directo</div>
                    <div style={{ fontSize: 28, fontWeight: 600 }}>Sofía M.</div>
                    <div style={{ marginTop: 10, fontSize: 24, color: c.mutedFg }}>Quienes le reportan</div>
                    {['Diana U.', 'Mariana L.'].map((n, i) => (
                        <div key={n} style={{ marginTop: 6, height: 50, borderRadius: radius.control, background: c.muted, border: `1px solid ${c.border}`, display: 'flex', alignItems: 'center', padding: '0 16px', fontSize: 27, fontWeight: 500, ...enter(f, audioAt + 12 + i * 6, { dur: dur.enter, y: 8 }) }}>{n}</div>
                    ))}
                    <div style={{ marginTop: 14, borderRadius: radius.control, background: c.accent, border: `1px solid ${ok > 0.5 ? c.primary : c.border}`, padding: '12px 14px', height: 164, boxSizing: 'border-box', ...pop(f, audioAt + 40) }}>
                        <div style={{ fontSize: 26, fontWeight: 600 }}>Mariana L.</div>
                        <div style={{ fontSize: 24, color: c.mutedFg }}>11 al 15 de enero</div>
                        <div style={{ position: 'relative', marginTop: 14, height: 52 }}>
                            <div style={{ position: 'absolute', inset: 0, display: 'flex', gap: 10, opacity: 1 - tween(f, CLICK + 2, 6) }}>
                                <div style={{ flex: 1, height: 52, display: 'flex', alignItems: 'center', justifyContent: 'center', borderRadius: radius.control, background: c.primary, color: c.primaryFg, fontSize: 26, fontWeight: 600, transform: `scale(${1 - 0.06 * press})` }}>Aprobar</div>
                                <div style={{ flex: 1, height: 52, boxSizing: 'border-box', display: 'flex', alignItems: 'center', justifyContent: 'center', borderRadius: radius.control, border: `2px solid color-mix(in srgb, ${c.fg} 38%, transparent)`, fontSize: 26, fontWeight: 600 }}>Rechazar</div>
                            </div>
                            <div style={{ position: 'absolute', inset: 0, display: 'flex', alignItems: 'center', gap: 12, fontSize: 28, fontWeight: 600, opacity: tween(f, CLICK + 8, 8) }}>
                                <CheckBadge size={36} /> Aprobada
                            </div>
                        </div>
                    </div>
                </div>
            </Phone>
            <Card style={{ left: 780, top: 250, width: 1030, height: 540, ...enter(f, 8, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 32, fontSize: 36, fontWeight: 600 }}>Lo que ve Laura M.</div>
                <div style={{ position: 'absolute', left: 44, top: 82 }}>
                    <Label>Solo a quienes le reportan</Label>
                </div>
            </Card>
            {T_L2.map((n) => (
                <Elbow key={n.name} from={T_ROOT} to={n.at} t={1} h={NH2} color={focus > 0.5 && !n.mine ? 'transparent' : undefined} />
            ))}
            {T_L3.map((n) => (
                <Elbow key={n.name} from={T_L2[n.parent]?.at ?? T_ROOT} to={n.at} t={1} h={NH2} color={n.mine ? c.primary : undefined} />
            ))}
            <OrgNode at={T_ROOT} name="Sofía M." role="Dirección" w={NW2} h={NH2} state={focus > 0.5 ? 'dim' : 'normal'} style={enter(f, audioAt + 6, { dur: dur.enter, y: 8 })} />
            {T_L2.map((n, i) => (
                <OrgNode key={n.name} at={n.at} name={n.name} role={n.role} w={NW2} h={NH2} state={stateOf(n.mine)} style={enter(f, audioAt + 10 + i * 4, { dur: dur.enter, y: 8 })} />
            ))}
            {T_L3.map((n, i) => (
                <OrgNode key={n.name} at={n.at} name={n.name} role={n.role} w={NW2} h={NH2} state={stateOf(n.mine)} style={enter(f, audioAt + 20 + i * 4, { dur: dur.enter, y: 8 })} />
            ))}
            <div style={{ position: 'absolute', left: 810, top: 700, display: 'flex', alignItems: 'center', gap: 12, fontSize: 26, color: c.mutedFg, opacity: focus }}>
                <Icon name="lock" size={30} color={c.primary} /> Al resto no lo ve
            </div>
            <div style={{ position: 'absolute', right: 150, top: 700, ...pop(f, CLICK + 40) }}>
                <Tag label="La ausencia entra al calendario" />
            </div>
            {Array.from({ length: 6 }, (_, k) => (
                <FlyDot key={k} f={f} start={CLICK + 10 + k * 4} len={30} from={{ x: PBOX.left + 180, y: 650 }} to={{ x: T_L3[3]?.at.x ?? 1700, y: (T_L3[3]?.at.y ?? 640) + 40 }} lift={60} size={14} />
            ))}
            <Cursor f={f} path={[{ at: audioAt + 58, x: 640, y: 760 }, { at: CLICK - 2, x: 410, y: 690 }]} clicks={[CLICK]} />
        </>
    );
};
