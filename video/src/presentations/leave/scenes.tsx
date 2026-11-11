import { easeInOut, enter, lerp, tween } from '../../ease';
import { Cursor, FlyDot, easeBack, pop, swapIn, swapOut } from '../../kit/fx';
import type { SceneProps } from '../../kit/schema';
import { useTheme } from '../../kit/themes';
import { dur, radius, stagger } from '../../kit/tokens';
import { Button, Card, CheckBadge, Icon, Meter, Phone, Tag } from '../../kit/ui';

const Label = ({ children }: { children: string }) => {
    const c = useTheme();
    return <div style={{ fontSize: 26, color: c.mutedFg }}>{children}</div>;
};

// ───────────────────────── 1. Tu saldo, por ley ─────────────────────────
// C-01 (art. 76): días por año de servicio. C-13: la ley es el piso; la política de la empresa solo puede mejorarla.
const TABLE = [
    { years: '1 año', days: 12 },
    { years: '2 años', days: 14 },
    { years: '3 años', days: 16 },
    { years: '4 años', days: 18 },
    { years: '5 años', days: 20 },
    { years: '6 a 10 años', days: 22 },
] as const;
const TROW = (i: number): number => 250 + 126 + i * 64;

export const BalanceScene = ({ f, audioAt }: SceneProps) => {
    const c = useTheme();
    const hit = tween(f, audioAt + 24, dur.morph, easeInOut);
    const law = Math.round(16 * tween(f, audioAt + 40, 40, (t) => t));
    const policy = tween(f, audioAt + 104, dur.enter, easeBack);
    const days = Math.round(lerp(law, 18, tween(f, audioAt + 112, 24, easeInOut)));
    return (
        <>
            <Card style={{ left: 110, top: 250, width: 700, height: 540, ...enter(f, 2, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 34, fontSize: 36, fontWeight: 600 }}>Artículo 76 de la LFT</div>
                <div style={{ position: 'absolute', left: 44, top: 82 }}>
                    <Label>Días por año de servicio</Label>
                </div>
            </Card>
            {TABLE.map((r, i) => {
                const on = i === 2 ? hit : 0;
                return (
                    <div key={r.years} style={{ position: 'absolute', left: 154, top: TROW(i), width: 612, height: 54, boxSizing: 'border-box', borderRadius: radius.control, background: `color-mix(in srgb, ${c.accent} ${on * 100}%, ${c.muted})`, border: `${on > 0.5 ? 2 : 1}px solid ${on > 0.5 ? c.primary : c.border}`, display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '0 24px', ...enter(f, audioAt + 4 + i * 4, { dur: dur.enter, y: 8 }) }}>
                        <div style={{ fontSize: 28, fontWeight: 500 }}>{r.years}</div>
                        <div style={{ fontSize: 30, fontWeight: 600, color: on > 0.5 ? c.primary : c.fg }}>{`${r.days} días`}</div>
                    </div>
                );
            })}
            <Card style={{ left: 850, top: 250, width: 960, height: 540, ...enter(f, 8, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 34, fontSize: 36, fontWeight: 600 }}>Saldo de Mariana L.</div>
                <div style={{ position: 'absolute', left: 44, top: 104 }}>
                    <Meter value={(days / 24) * 100} size={300}>
                        <div style={{ fontSize: 96, fontWeight: 600, lineHeight: 1 }}>{days}</div>
                        <div style={{ fontSize: 26, color: c.mutedFg }}>días</div>
                    </Meter>
                </div>
                {[
                    { title: 'Ley (art. 76)', value: '16 días', at: audioAt + 56, win: false },
                    { title: 'Política de tu empresa', value: '18 días', at: audioAt + 96, win: true },
                ].map((t, i) => {
                    const g = tween(f, t.at, dur.enter, easeBack);
                    const on = t.win ? policy : 0;
                    return (
                        <div key={t.title} style={{ position: 'absolute', left: 380, right: 44, top: 108 + i * 138, height: 120, boxSizing: 'border-box', borderRadius: radius.control, background: `color-mix(in srgb, ${c.accent} ${on * 100}%, ${c.muted})`, border: `${on > 0.5 ? 2 : 1}px solid ${on > 0.5 ? c.primary : c.border}`, padding: '18px 24px', opacity: g, transform: `translateY(${(1 - g) * 12}px)` }}>
                            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                                <div style={{ fontSize: 26, color: c.mutedFg }}>{t.title}</div>
                                {t.win && on > 0.5 ? <CheckBadge size={34} /> : null}
                            </div>
                            <div style={{ marginTop: 4, fontSize: 44, fontWeight: 600 }}>{t.value}</div>
                        </div>
                    );
                })}
                <div style={{ position: 'absolute', left: 44, right: 44, bottom: 36, fontSize: 28, lineHeight: 1.3, color: c.mutedFg, opacity: tween(f, audioAt + 120, dur.enter) }}>La ley es el piso: la política solo puede mejorarla.</div>
            </Card>
            {Array.from({ length: 8 }, (_, k) => (
                <FlyDot key={k} f={f} start={audioAt + 28 + k * 3} len={28} from={{ x: 700, y: TROW(2) + 29 }} to={{ x: 1000, y: 500 }} lift={70} size={16} />
            ))}
        </>
    );
};

// ───────────────────────── 2. Solicita desde el portal ─────────────────────────
// La persona elige los días en el calendario de su portal y envía; se avisa (no se bloquea) si el periodo no tiene 12 días seguidos.
const GRID: readonly (number | null)[] = [null, null, null, null, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31];
const PHONE = { left: 300, top: 236, w: 360, h: 574 } as const;
const SEL = [11, 12, 13, 14, 15] as const;

export const RequestScene = ({ f, audioAt }: SceneProps) => {
    const c = useTheme();
    const selAt = (n: number): number => audioAt + 22 + SEL.indexOf(n as (typeof SEL)[number]) * 7;
    const CLICK = audioAt + 84;
    const press = tween(f, CLICK - 3, 6) * (1 - tween(f, CLICK + 3, 6));
    const sent = tween(f, CLICK + 6, 14, easeInOut);
    const left = Math.round(lerp(18, 13, tween(f, CLICK + 24, 26, easeInOut)));
    return (
        <>
            <Phone style={{ left: PHONE.left, top: PHONE.top, width: PHONE.w, height: PHONE.h, ...enter(f, 2, { dur: dur.scene, x: -30, y: 0 }) }}>
                <div style={{ position: 'absolute', left: 22, right: 22, top: 62, opacity: swapOut(sent) }}>
                    <div style={{ fontSize: 28, fontWeight: 600 }}>Enero de 2027</div>
                    <div style={{ marginTop: 10, display: 'grid', gridTemplateColumns: 'repeat(7, 1fr)', textAlign: 'center', fontSize: 24, color: c.mutedFg }}>
                        {['L', 'M', 'M', 'J', 'V', 'S', 'D'].map((d, i) => (
                            <div key={i}>{d}</div>
                        ))}
                    </div>
                    <div style={{ marginTop: 6, display: 'grid', gridTemplateColumns: 'repeat(7, 1fr)', rowGap: 4, columnGap: 4, textAlign: 'center' }}>
                        {GRID.map((n, i) => {
                            const on = n !== null && (SEL as readonly number[]).includes(n) ? tween(f, selAt(n), dur.micro + 2) : 0;
                            return (
                                <div key={i} style={{ height: 42, display: 'flex', alignItems: 'center', justifyContent: 'center', borderRadius: 10, fontSize: 26, fontWeight: on > 0.5 ? 600 : 500, color: on > 0.5 ? c.primaryFg : n === null ? 'transparent' : c.fg, background: `color-mix(in srgb, ${c.primary} ${on * 100}%, transparent)` }}>{n ?? ''}</div>
                            );
                        })}
                    </div>
                    <div style={{ marginTop: 14, fontSize: 28, fontWeight: 600, opacity: tween(f, audioAt + 58, dur.enter) }}>5 días laborables</div>
                    <Button label="Enviar solicitud" pressed={press} style={{ marginTop: 14, width: '100%', justifyContent: 'center', boxSizing: 'border-box', opacity: tween(f, audioAt + 60, dur.enter) }} />
                </div>
                <div style={{ position: 'absolute', left: 26, right: 26, top: 150, opacity: swapIn(sent), textAlign: 'center' }}>
                    <CheckBadge size={96} style={{ margin: '0 auto', transform: `scale(${lerp(0.7, 1, sent)})` }} />
                    <div style={{ marginTop: 24, fontSize: 30, fontWeight: 600 }}>Solicitud enviada</div>
                    <div style={{ marginTop: 8, fontSize: 26, color: c.mutedFg }}>11 al 15 de enero</div>
                </div>
            </Phone>
            <Card style={{ left: 780, top: 250, width: 1030, height: 540, ...enter(f, 8, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 34, fontSize: 38, fontWeight: 600 }}>Tu solicitud</div>
                {[
                    ['Fechas', '11 al 15 de enero', audioAt + 30],
                    ['Días', '5 laborables', audioAt + 46],
                    ['Saldo después', `${left} días`, CLICK + 12],
                ].map(([k, v, at], i) => {
                    const g = tween(f, Number(at), dur.enter, easeBack);
                    return (
                        <div key={String(k)} style={{ position: 'absolute', left: 44 + i * 322, top: 108, width: 302, height: 120, boxSizing: 'border-box', borderRadius: radius.control, background: c.muted, border: `1px solid ${c.border}`, padding: '18px 20px', opacity: g, transform: `translateY(${(1 - g) * 14}px)` }}>
                            <div style={{ fontSize: 26, color: c.mutedFg }}>{String(k)}</div>
                            <div style={{ marginTop: 6, fontSize: 31, fontWeight: 600, whiteSpace: 'nowrap' }}>{String(v)}</div>
                        </div>
                    );
                })}
                <div style={{ position: 'absolute', left: 44, right: 44, top: 256, height: 132, boxSizing: 'border-box', borderRadius: radius.control, background: c.warnBg, border: `1px solid ${c.warn}`, padding: '20px 26px', color: c.warnFg, opacity: tween(f, CLICK + 30, dur.enter) }}>
                    <div style={{ fontSize: 30, fontWeight: 600 }}>Aviso: aún no hay 12 días seguidos</div>
                    <div style={{ marginTop: 8, fontSize: 26, lineHeight: 1.3 }}>Se avisa, no se bloquea: repartir el periodo es decisión de la persona.</div>
                </div>
                <div style={{ position: 'absolute', left: 44, bottom: 40, ...pop(f, CLICK + 46) }}>
                    <Tag label="Pendiente de aprobación" />
                </div>
            </Card>
            {Array.from({ length: 7 }, (_, k) => (
                <FlyDot key={k} f={f} start={CLICK + 8 + k * 3} len={26} from={{ x: PHONE.left + 180, y: 560 }} to={{ x: 1000 + (k % 3) * 40, y: 400 }} lift={70} size={16} />
            ))}
            <Cursor f={f} path={[{ at: audioAt + 56, x: 640, y: 700 }, { at: CLICK - 2, x: 480, y: 706 }]} clicks={[CLICK]} />
        </>
    );
};

// ───────────────────────── 3. Aprobada y a la vista ─────────────────────────
// C-18: aprueba el jefe directo; RH es respaldo a los tres días hábiles. La ausencia aparece en el calendario del equipo.
const TEAM = [
    { name: 'Mariana L.', out: [11, 12, 13, 14, 15], mine: true },
    { name: 'Jorge R.', out: [16, 17], mine: false },
    { name: 'Lucía H.', out: [], mine: false },
    { name: 'Diana U.', out: [18, 19], mine: false },
] as const;
const DAYSN = [11, 12, 13, 14, 15, 16, 17, 18, 19, 20] as const;
const GX = 850 + 44 + 190;
const GROW = (i: number): number => 250 + 156 + i * 84;

export const ApproveScene = ({ f, audioAt }: SceneProps) => {
    const c = useTheme();
    const CLICK = audioAt + 46;
    const press = tween(f, CLICK - 3, 6) * (1 - tween(f, CLICK + 3, 6));
    const ok = tween(f, CLICK + 6, dur.morph, easeBack);
    return (
        <>
            <Card style={{ left: 110, top: 250, width: 700, height: 540, ...enter(f, 2, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 34, fontSize: 36, fontWeight: 600 }}>Solicitud de Mariana L.</div>
                <div style={{ position: 'absolute', left: 44, top: 96, fontSize: 30, fontWeight: 500 }}>11 al 15 de enero</div>
                <div style={{ position: 'absolute', left: 44, top: 140 }}>
                    <Label>5 días laborables · Vacaciones</Label>
                </div>
                <div style={{ position: 'absolute', left: 44, top: 224, display: 'flex', alignItems: 'center', gap: 16 }}>
                    <div style={{ opacity: swapOut(ok) }}>
                        <div style={{ display: 'flex', gap: 16 }}>
                            <Button label="Aprobar" pressed={press} />
                            <div style={{ height: 64, padding: '0 28px', display: 'flex', alignItems: 'center', borderRadius: radius.control, border: `1px solid ${c.border}`, fontSize: 28, fontWeight: 600 }}>Rechazar</div>
                        </div>
                    </div>
                </div>
                <div style={{ position: 'absolute', left: 44, top: 224, display: 'flex', alignItems: 'center', gap: 14, height: 64, padding: '0 24px', borderRadius: radius.control, background: c.accent, border: `1px solid ${c.primary}`, fontSize: 30, fontWeight: 600, opacity: swapIn(ok), transform: `scale(${lerp(0.9, 1, ok)})`, transformOrigin: 'left center' }}>
                    <CheckBadge size={38} /> Aprobada
                </div>
                <div style={{ position: 'absolute', left: 44, right: 44, bottom: 40, fontSize: 26, lineHeight: 1.35, color: c.mutedFg }}>Si el jefe no responde en 3 días hábiles, le llega a RH.</div>
            </Card>
            <Card style={{ left: 850, top: 250, width: 960, height: 540, ...enter(f, 8, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 34, fontSize: 36, fontWeight: 600 }}>Calendario del equipo</div>
                <div style={{ position: 'absolute', left: 44 + 190, top: 100, display: 'flex' }}>
                    {DAYSN.map((d) => (
                        <div key={d} style={{ width: 62, textAlign: 'center', fontSize: 26, color: c.mutedFg }}>{d}</div>
                    ))}
                </div>
            </Card>
            {TEAM.map((p, i) => (
                <div key={p.name} style={{ position: 'absolute', left: 894, top: GROW(i), width: 872, height: 72, boxSizing: 'border-box', borderRadius: radius.control, background: c.muted, border: `1px solid ${c.border}`, ...enter(f, audioAt + 6 + i * stagger, { dur: dur.enter, y: 10 }) }}>
                    <div style={{ position: 'absolute', left: 22, top: 18, fontSize: 28, fontWeight: 600 }}>{p.name}</div>
                    {DAYSN.map((d, k) => {
                        const out = (p.out as readonly number[]).includes(d);
                        const g = p.mine ? (out ? tween(f, CLICK + 24 + (d - 11) * 5, dur.micro + 4) : 0) : out ? 1 : 0;
                        return (
                            <div key={d} style={{ position: 'absolute', left: 190 + k * 62 + 5, top: 12, width: 52, height: 48, borderRadius: 10, background: `color-mix(in srgb, ${p.mine ? c.primary : c.mutedFg} ${g * (p.mine ? 100 : 55)}%, transparent)`, transform: `scale(${1 + (p.mine ? 0.12 : 0) * Math.sin(g * Math.PI)})` }} />
                        );
                    })}
                </div>
            ))}
            {Array.from({ length: 5 }, (_, k) => (
                <FlyDot key={k} f={f} start={CLICK + 12 + k * 5} len={26} from={{ x: 260, y: 540 }} to={{ x: GX + 31 + k * 62, y: GROW(0) + 36 }} lift={90} size={16} />
            ))}
            <Cursor f={f} path={[{ at: audioAt + 22, x: 700, y: 640 }, { at: CLICK - 2, x: 200, y: 520 }]} clicks={[CLICK]} />
        </>
    );
};

// ───────────────────────── 4. Todo en el expediente ─────────────────────────
// C-10: constancia anual (antigüedad, días, periodo de disfrute, prima) con acuse de la persona; C-12: nada se borra.
const SHEET = [
    ['Persona', 'Mariana L.'],
    ['Antigüedad', '3 años'],
    ['Vacaciones', '18 días laborables'],
    ['Disfrute', 'Del 12 de marzo al 12 de septiembre'],
    ['Prima', '25 %'],
] as const;

export const EvidenceScene = ({ f, audioAt }: SceneProps) => {
    const c = useTheme();
    const CLICK = audioAt + 104;
    const press = tween(f, CLICK - 3, 6) * (1 - tween(f, CLICK + 3, 6));
    const ack = tween(f, CLICK + 8, dur.morph, easeBack);
    const items = [
        { title: 'Solicitud · 11 al 15 de enero', state: 'Aprobada', at: audioAt + 56 },
        { title: 'Constancia anual', state: 'Emitida', at: audioAt + 74 },
        { title: 'Acuse de la persona', state: 'Confirmado', at: CLICK + 10 },
    ] as const;
    return (
        <>
            <Card style={{ left: 110, top: 250, width: 820, height: 540, ...enter(f, 2, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 34, display: 'flex', alignItems: 'center', gap: 16, fontSize: 36, fontWeight: 600 }}>
                    <Icon name="file" size={40} color={c.primary} /> Constancia de vacaciones
                </div>
                {SHEET.map(([k, v], i) => (
                    <div key={k} style={{ position: 'absolute', left: 44, right: 44, top: 100 + i * 66, height: 58, display: 'flex', alignItems: 'center', justifyContent: 'space-between', borderBottom: `1px solid ${c.border}`, ...enter(f, audioAt + 8 + i * stagger, { dur: dur.enter, y: 8 }) }}>
                        <div style={{ fontSize: 26, color: c.mutedFg }}>{k}</div>
                        <div style={{ fontSize: 28, fontWeight: 600, textAlign: 'right' }}>{v}</div>
                    </div>
                ))}
                <div style={{ position: 'absolute', left: 44, bottom: 36, fontSize: 26, color: c.mutedFg }}>Léala y confirme que la recibió.</div>
                <div style={{ position: 'absolute', right: 44, bottom: 24, opacity: swapOut(ack) }}>
                    <Button label="Ya la recibí" pressed={press} />
                </div>
                <div style={{ position: 'absolute', right: 44, bottom: 24, display: 'flex', alignItems: 'center', gap: 12, height: 64, padding: '0 22px', borderRadius: radius.control, background: c.accent, border: `1px solid ${c.primary}`, fontSize: 28, fontWeight: 600, opacity: swapIn(ack), transform: `scale(${lerp(0.9, 1, ack)})`, transformOrigin: 'right center' }}>
                    <CheckBadge size={34} /> Recibida
                </div>
            </Card>
            <Card style={{ left: 970, top: 250, width: 840, height: 540, ...enter(f, 8, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 34, fontSize: 36, fontWeight: 600 }}>Expediente de Mariana L.</div>
                {items.map((it, i) => {
                    const g = tween(f, it.at, dur.enter, easeBack);
                    return (
                        <div key={it.title} style={{ position: 'absolute', left: 44, right: 44, top: 108 + i * 108, height: 92, boxSizing: 'border-box', borderRadius: radius.control, background: c.muted, border: `1px solid ${g > 0.5 ? c.primary : c.border}`, display: 'flex', alignItems: 'center', gap: 18, padding: '0 24px', opacity: g, transform: `translateX(${(1 - g) * 24}px)` }}>
                            <CheckBadge size={44} />
                            <div style={{ flex: 1 }}>
                                <div style={{ fontSize: 28, fontWeight: 600 }}>{it.title}</div>
                                <div style={{ fontSize: 26, color: c.primary }}>{it.state}</div>
                            </div>
                        </div>
                    );
                })}
                <div style={{ position: 'absolute', left: 44, bottom: 40, display: 'flex', alignItems: 'center', gap: 12, ...pop(f, audioAt + 130) }}>
                    <Tag label="Nada se borra" />
                </div>
            </Card>
            {Array.from({ length: 6 }, (_, k) => (
                <FlyDot key={k} f={f} start={audioAt + 50 + k * 4} len={26} from={{ x: 700, y: 400 }} to={{ x: 1100, y: 330 + (k % 3) * 100 }} lift={70} size={16} />
            ))}
            <Cursor f={f} path={[{ at: audioAt + 82, x: 1700, y: 700 }, { at: CLICK - 2, x: 820, y: 740 }]} clicks={[CLICK]} />
        </>
    );
};
