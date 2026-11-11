import { easeInOut, enter, lerp, tween } from '../../ease';
import { Cursor, FlyDot, Toast, easeBack, pop, type Pt } from '../../kit/fx';
import { Reveal } from '../../kit/primitives';
import type { SceneProps } from '../../kit/schema';
import { useTheme } from '../../kit/themes';
import { dur, radius, stagger } from '../../kit/tokens';
import { Button, Card, CheckBadge, Icon, Meter, Tag } from '../../kit/ui';

const Label = ({ children }: { children: string }) => {
    const c = useTheme();
    return <div style={{ fontSize: 28, color: c.mutedFg }}>{children}</div>;
};

// ───────────────────────── 1. Cada centro, sus guías ─────────────────────────
// num. 4.4: ≤15 personas → Guía I; 16 a 50 → I y II; más de 50 → I y III.
const CENTERS = [
    { name: 'Oficinas Monterrey', area: 'Administración y ventas', people: 60, guides: 'Guías I y III' },
    { name: 'Planta Querétaro', area: 'Manufactura de autopartes', people: 30, guides: 'Guías I y II' },
    { name: 'Bodega Toluca', area: 'Almacén', people: 12, guides: 'Guía I' },
] as const;
const CARD = { top: 330, h: 440, w: 540, left: (i: number): number => 110 + i * 580 } as const;
const SOURCE: Pt = { x: 960, y: 282 };
const GAP = 0.6; // frames entre salidas de puntos
const FLIGHT = 24;

/** Orden de salida: los centros se llenan a la par, en proporción a su tamaño. */
const ORDER: readonly number[] = (() => {
    const done = CENTERS.map(() => 0);
    const out: number[] = [];
    for (let k = 0; k < CENTERS.reduce((n, c) => n + c.people, 0); k++) {
        let best = 0;
        let ratio = Infinity;
        CENTERS.forEach((c, j) => {
            const d = done[j] ?? 0;
            if (d < c.people && d / c.people < ratio) {
                best = j;
                ratio = d / c.people;
            }
        });
        done[best] = (done[best] ?? 0) + 1;
        out.push(best);
    }
    return out;
})();

export const GuidesScene = ({ f, audioAt }: SceneProps) => {
    const c = useTheme();
    const start = audioAt + 10;
    const arrival = (k: number): number => start + k * GAP + FLIGHT;
    const seen = CENTERS.map(() => 0);
    const last = CENTERS.map(() => 0);
    const dots = ORDER.map((j, k) => {
        seen[j] = (seen[j] ?? 0) + 1;
        last[j] = k;
        const m = seen[j] ?? 1;
        const center = CENTERS[j];
        return { j, k, to: { x: CARD.left(j) + 44 + (452 * m) / 60, y: CARD.top + 316 } satisfies Pt, count: m, people: center?.people ?? 1 };
    });
    const count = (j: number): number => dots.filter((d) => d.j === j && arrival(d.k) <= f).length;
    return (
        <>
            <div style={{ position: 'absolute', left: SOURCE.x - 225, top: SOURCE.y - 28, width: 450, height: 56, display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 12, borderRadius: 999, background: c.accent, border: `1px solid ${c.border}`, fontSize: 28, fontWeight: 500, ...pop(f, audioAt - 4) }}>
                <Icon name="listCheck" size={28} color={c.primary} /> Tu plantilla · 102 personas
            </div>
            {CENTERS.map((ctr, j) => {
                const n = count(j);
                const chipAt = arrival(last[j] ?? 0) + 4;
                const full = tween(f, arrival(last[j] ?? 0), 10);
                return (
                    <Card key={ctr.name} style={{ left: CARD.left(j), top: CARD.top, width: CARD.w, height: CARD.h, padding: 44, ...enter(f, audioAt + j * stagger, { dur: dur.scene, y: 18 }) }}>
                        <div style={{ fontSize: 38, fontWeight: 600 }}>{ctr.name}</div>
                        <div style={{ marginTop: 4, fontSize: 26, color: c.mutedFg }}>{ctr.area}</div>
                        <div style={{ marginTop: 22, display: 'flex', alignItems: 'baseline', gap: 14 }}>
                            <div style={{ fontSize: 104, fontWeight: 600, lineHeight: 1, letterSpacing: -2 }}>{n}</div>
                            <div style={{ fontSize: 32, color: c.mutedFg }}>personas</div>
                        </div>
                        <div style={{ position: 'absolute', left: 44, right: 44, top: 286, height: 12, borderRadius: 99, background: c.track }}>
                            <div style={{ height: '100%', borderRadius: 99, background: c.primary, width: `${(n / 60) * 100}%`, boxShadow: `0 0 ${14 * full}px color-mix(in srgb, ${c.primary} 55%, transparent)` }} />
                        </div>
                        <div style={{ position: 'absolute', left: 44, bottom: 40, display: 'flex', alignItems: 'center', gap: 16, ...pop(f, chipAt) }}>
                            <CheckBadge size={52} />
                            <div style={{ fontSize: 44, fontWeight: 600, letterSpacing: -0.5 }}>{ctr.guides}</div>
                        </div>
                    </Card>
                );
            })}
            {dots.map((d) => (
                <FlyDot key={d.k} f={f} start={start + d.k * GAP} len={FLIGHT} from={{ x: SOURCE.x, y: SOURCE.y + 30 }} to={d.to} lift={70 + d.j * 20} size={17} />
            ))}
            <Reveal f={f} at={audioAt + 24} style={{ position: 'absolute', left: 110, top: 800, fontSize: 28, color: c.mutedFg }}>
                Hasta 15 personas: Guía I · De 16 a 50: Guías I y II · Más de 50: Guías I y III
            </Reveal>
        </>
    );
};

// ───────────────────────── 2. Una muestra justa, sorteada ─────────────────────────
// n = 0.9604·N / (0.0025·(N−1) + 0.9604); N = 60 → n = 52.
const OUT = new Set([3, 11, 17, 26, 34, 41, 49, 56]);
const GRID = { left: 720, top: 250, w: 1090, x0: 787, y0: 392, px: 106.2, py: 62 } as const;
const slot = (i: number): Pt => ({ x: GRID.x0 + (i % 10) * GRID.px, y: GRID.y0 + Math.floor(i / 10) * GRID.py });

export const SampleScene = ({ f, audioAt }: SceneProps) => {
    const c = useTheme();
    const S = audioAt + 62;
    const lit = (i: number): number => (OUT.has(i) ? 0 : tween(f, S + (i % 10) * 2.4 + Math.floor(i / 10) * 1.2, 8));
    const n = Array.from({ length: 60 }, (_, i) => lit(i)).filter((t) => t > 0.5).length;
    const shuffle = tween(f, S - 24, 6) * (1 - tween(f, S - 2, 4));
    return (
        <>
            <Card style={{ left: 110, top: 250, width: 560, height: 540, padding: 44, ...enter(f, 2, { dur: dur.scene, y: 18 }) }}>
                <div style={{ fontSize: 40, fontWeight: 600 }}>Oficinas Monterrey</div>
                <div style={{ marginTop: 28 }}>
                    <Label>Personal</Label>
                    <div style={{ fontSize: 56, fontWeight: 600 }}>60 personas</div>
                </div>
                <div style={{ marginTop: 30 }}>
                    <Label>Muestra para la Guía III</Label>
                    <div style={{ fontSize: 128, fontWeight: 600, lineHeight: 1.05, letterSpacing: -3, color: c.primary }}>{n}</div>
                </div>
                <Tag label="Proporcional por género" style={{ position: 'absolute', left: 44, bottom: 44, ...pop(f, S + 20) }} />
            </Card>
            <Card style={{ left: GRID.left, top: GRID.top, width: GRID.w, height: 540, padding: 44, ...enter(f, 8, { dur: dur.scene, y: 18 }) }}>
                <div style={{ fontSize: 40, fontWeight: 600 }}>Selección aleatoria</div>
            </Card>
            {Array.from({ length: 60 }, (_, i) => {
                const p = slot(i);
                const at = audioAt + 4 + i * 0.5;
                const arrived = tween(f, at + 18, 6);
                const t = lit(i);
                const flick = shuffle * (Math.sin(f * 0.9 + i * 1.7) > 0.5 ? 0.75 : 0);
                const dim = OUT.has(i) ? tween(f, S + 34 + (i % 10) * 1.5, 10) : 0;
                const mix = Math.max(t, flick) * 100;
                return (
                    <div key={i}>
                        <FlyDot f={f} start={at} len={22} from={{ x: 280, y: 410 }} to={p} lift={50} size={18} />
                        <div style={{ position: 'absolute', left: p.x - 23, top: p.y - 23, width: 46, height: 46, borderRadius: 99, background: `color-mix(in srgb, ${c.primary} ${mix}%, ${c.track})`, opacity: arrived * (1 - 0.65 * dim), transform: `scale(${lerp(1, 0.78, dim) * (1 + 0.14 * Math.sin(t * Math.PI))})`, boxShadow: t > 0.5 ? `0 0 18px color-mix(in srgb, ${c.primary} 45%, transparent)` : 'none' }} />
                    </div>
                );
            })}
        </>
    );
};

// ───────────────────────── 3. Avanza sin ver respuestas ─────────────────────────
const METER = { x: 260, y: 350, s: 340 } as const;
/** Cabeza del arco de progreso para un avance `v` (0–100), en píxeles (la forma «h» del logo). */
const head = (v: number): Pt => {
    const dist = (v / 100) * 101.1;
    const k = METER.s / 64;
    const at = (x: number, y: number): Pt => ({ x: METER.x + x * k, y: METER.y + y * k });
    if (dist < 16) {
        return at(10, 49 - dist);
    }
    if (dist < 85.1) {
        const phi = (dist - 16) / 69.1;
        return at(32 - 22 * Math.cos(Math.PI * phi), 33 - 22 * Math.sin(Math.PI * phi));
    }
    return at(54, 33 + (dist - 85.1));
};

const PEOPLE = [
    { name: 'Alexandra Olivas', initials: 'AO', area: 'Ventas' },
    { name: 'Alma Nava', initials: 'AN', area: 'Ventas' },
    { name: 'Blanca Osorio', initials: 'BO', area: 'Ventas' },
] as const;
const ROW_Y = (i: number): number => 392 + i * 100 + 43;

export const InvitesScene = ({ f, audioAt }: SceneProps) => {
    const c = useTheme();
    const A0 = audioAt + 4;
    const arrive = (k: number): number => A0 + k * 1.5 + 26;
    const arrived = Array.from({ length: 18 }, (_, k) => k).filter((k) => arrive(k) <= f).length;
    const CLICK = audioAt + 92;
    const press = tween(f, CLICK - 3, 6) * (1 - tween(f, CLICK + 3, 6));
    const sentAt = (i: number): number => CLICK + 14 + i * 7;
    const toast = tween(f, CLICK + 6, dur.enter, easeBack);
    const sent = Math.round(42 * tween(f, CLICK + 22, 70, (t) => t));
    return (
        <>
            <Card style={{ left: 110, top: 250, width: 640, height: 540, padding: 44, ...enter(f, 2, { dur: dur.scene, y: 18 }) }}>
                <Label>Avance de Oficinas Monterrey</Label>
                <div style={{ position: 'absolute', left: 150, top: 100 }}>
                    <Meter value={(arrived / 60) * 100} size={METER.s}>
                        <div style={{ fontSize: 88, fontWeight: 600, lineHeight: 1 }}>{arrived}</div>
                        <div style={{ fontSize: 26, lineHeight: 1.2, color: c.mutedFg, textAlign: 'center' }}>de 60<br />contestaron</div>
                    </Meter>
                </div>
                <div style={{ position: 'absolute', left: 44, right: 44, bottom: 40, fontSize: 28, lineHeight: 1.35, color: c.mutedFg }}>Aquí ves cuántas personas contestaron, nunca qué contestaron.</div>
            </Card>
            {Array.from({ length: 18 }, (_, k) => (
                <FlyDot key={k} f={f} start={A0 + k * 1.5} len={26} from={{ x: -30, y: 330 + ((k * 47) % 380) }} to={head(((k + 1) / 60) * 100)} lift={30} size={16} />
            ))}
            <Card style={{ left: 800, top: 250, width: 1010, height: 540, padding: 44, ...enter(f, 8, { dur: dur.scene, y: 18 }) }}>
                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', height: 64 }}>
                    <div style={{ fontSize: 40, fontWeight: 600 }}>
                        Pendientes <span style={{ color: c.mutedFg, fontWeight: 400 }}>· 42</span>
                    </div>
                    <Button label="Recordar a 42" pressed={press} />
                </div>
                {PEOPLE.map((p, i) => {
                    const s = tween(f, sentAt(i), dur.enter);
                    return (
                        <div key={p.name} style={{ marginTop: i === 0 ? 34 : 14, height: 86, borderRadius: radius.control, background: c.muted, border: `1px solid ${s > 0.5 ? c.primary : c.border}`, display: 'flex', alignItems: 'center', gap: 20, padding: '0 24px', boxSizing: 'border-box', ...enter(f, audioAt + 26 + i * stagger, { dur: dur.enter, y: 10 }) }}>
                            <div style={{ width: 52, height: 52, borderRadius: 99, background: c.accent, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 22, fontWeight: 600 }}>{p.initials}</div>
                            <div style={{ flex: 1 }}>
                                <div style={{ fontSize: 30, fontWeight: 500 }}>{p.name}</div>
                                <div style={{ fontSize: 24, color: c.mutedFg }}>{p.area}</div>
                            </div>
                            <div style={{ position: 'relative', width: 330, height: 40 }}>
                                <div style={{ position: 'absolute', right: 0, fontSize: 26, color: c.mutedFg, opacity: 1 - s }}>Pendiente</div>
                                <div style={{ position: 'absolute', right: 0, display: 'flex', alignItems: 'center', gap: 10, fontSize: 26, fontWeight: 500, whiteSpace: 'nowrap', ...pop(f, sentAt(i)) }}>
                                    <Icon name="check" size={28} color={c.primary} stroke={2.6} /> Recordatorio enviado
                                </div>
                            </div>
                        </div>
                    );
                })}
            </Card>
            {PEOPLE.map((p, i) => (
                <FlyDot key={p.name} f={f} start={CLICK + 2 + i * 5} len={18} from={{ x: 1640, y: 318 }} to={{ x: 1480, y: ROW_Y(i) }} lift={40} size={22} />
            ))}
            <Toast style={{ left: 844, top: 692, width: 922, height: 84, opacity: toast, transform: `translateY(${(1 - toast) * 24}px)` }}>
                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', fontSize: 28, fontWeight: 500 }}>
                    <span style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                        <Icon name="mail" size={30} color={c.primary} /> Enviando recordatorios
                    </span>
                    <span>{sent} de 42</span>
                </div>
                <div style={{ marginTop: 12, height: 8, borderRadius: 99, background: c.track }}>
                    <div style={{ height: '100%', borderRadius: 99, background: c.primary, width: `${(sent / 42) * 100}%` }} />
                </div>
            </Toast>
            <Cursor f={f} path={[{ at: audioAt + 64, x: 1760, y: 560 }, { at: CLICK - 2, x: 1650, y: 336 }]} clicks={[CLICK]} />
        </>
    );
};

// ───────────────────────── 4. Del dato al informe ─────────────────────────
const SECTIONS = ['Datos del centro', 'Objetivo', 'Actividades', 'Método', 'Resultados', 'Conclusiones', 'Acciones de intervención', 'Responsable'] as const;
export const ReportScene = ({ f, audioAt }: SceneProps) => {
    const c = useTheme();
    const at = (i: number): number => audioAt + 22 + i * 9;
    const done = SECTIONS.filter((_, i) => f >= at(i) + 6).length;
    const finished = tween(f, at(7) + 10, 14, easeInOut);
    return (
        <>
            <Card style={{ left: 110, top: 250, width: 1000, height: 540, padding: 44, transform: `translateY(${-8 * finished}px) scale(${1 + 0.01 * finished})`, boxShadow: `0 ${24 * finished}px ${50 * finished}px rgba(0,0,0,${0.28 * finished})`, ...enter(f, 2, { dur: dur.scene, y: 18 }) }}>
                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 18 }}>
                        <Icon name="file" size={44} color={c.primary} />
                        <div style={{ fontSize: 42, fontWeight: 600 }}>Informe · numeral 7.7</div>
                    </div>
                    <div style={{ fontSize: 34, fontWeight: 600, color: c.primary }}>{done} de 8</div>
                </div>
                <div style={{ marginTop: 20, height: 8, borderRadius: 99, background: c.track }}>
                    <div style={{ height: '100%', borderRadius: 99, background: c.primary, width: `${(done / 8) * 100}%` }} />
                </div>
                <div style={{ marginTop: 28, display: 'grid', gridTemplateColumns: '1fr 1fr', columnGap: 32, rowGap: 14 }}>
                    {SECTIONS.map((s, i) => {
                        const on = tween(f, at(i), dur.micro + 4);
                        return (
                            <div key={s} style={{ height: 76, borderRadius: radius.control, background: c.muted, border: `1px solid ${on > 0.5 ? c.primary : c.border}`, display: 'flex', alignItems: 'center', gap: 16, padding: '0 20px', boxSizing: 'border-box' }}>
                                <div style={{ width: 40, height: 40, borderRadius: 99, flexShrink: 0, background: `color-mix(in srgb, ${c.primary} ${on * 100}%, ${c.track})`, display: 'flex', alignItems: 'center', justifyContent: 'center', transform: `scale(${1 + 0.18 * Math.sin(on * Math.PI)})` }}>
                                    <div style={{ opacity: on, display: 'flex' }}>
                                        <Icon name="check" size={24} color={c.primaryFg} stroke={3} />
                                    </div>
                                </div>
                                <div style={{ fontSize: 28, fontWeight: 500, lineHeight: 1.15 }}>{s}</div>
                            </div>
                        );
                    })}
                </div>
            </Card>
            <Card style={{ left: 1160, top: 250, width: 650, height: 540, padding: 44, ...enter(f, 12, { dur: dur.scene, x: 40, y: 0 }) }}>
                <div style={{ width: 84, height: 84, borderRadius: radius.card, background: c.accent, display: 'flex', alignItems: 'center', justifyContent: 'center', ...pop(f, audioAt + 50) }}>
                    <Icon name="lock" size={46} color={c.primary} />
                </div>
                <div style={{ marginTop: 34, fontSize: 42, fontWeight: 600, lineHeight: 1.15 }}>Canalización de la Guía I</div>
                <div style={{ marginTop: 18, fontSize: 30, lineHeight: 1.35, color: c.mutedFg }}>Solo la ven las personas autorizadas. El informe nunca lleva respuestas individuales.</div>
                <Tag label="Evidencia para la STPS" style={{ position: 'absolute', left: 44, bottom: 44, ...pop(f, at(7) + 12) }} />
            </Card>
        </>
    );
};
