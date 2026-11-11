import { easeInOut, enter, lerp, tween } from '../../ease';
import { Cursor, FlyDot, easeBack, pop } from '../../kit/fx';
import type { SceneProps } from '../../kit/schema';
import { useTheme } from '../../kit/themes';
import { dur, radius, stagger } from '../../kit/tokens';
import { Button, Card, CheckBadge, Icon, Tag } from '../../kit/ui';

const money = (n: number): string => `$${Math.round(n).toLocaleString('en-US')}`;
const millions = (n: number): string => `$${(n / 1_000_000).toFixed(2)} M`;

const Label = ({ children }: { children: string }) => {
    const c = useTheme();
    return <div style={{ fontSize: 26, color: c.mutedFg }}>{children}</div>;
};

/** Todas las cifras de estas escenas son de demostración; se rotulan así, arriba a la derecha. */
const SampleTag = ({ f, at }: { f: number; at: number }) => (
    <div style={{ position: 'absolute', right: 110, top: 142, ...pop(f, at) }}>
        <Tag label="Cifras de ejemplo" />
    </div>
);

// ───────────────────────── 1. Del aumento al costo ─────────────────────────
// Un aumento del 6 % costeado con salario, prestaciones, IMSS, Infonavit e impuesto sobre nómina (cifras de ejemplo, coherentes).
const COSTS = [
    { label: 'Salario', amount: 1_080_000 },
    { label: 'Prestaciones', amount: 216_000 },
    { label: 'IMSS', amount: 142_000 },
    { label: 'Infonavit', amount: 54_000 },
    { label: 'Impuesto sobre nómina', amount: 32_400 },
] as const;
const RROW = (i: number): number => 384 + i * 62;

export const CostScene = ({ f, audioAt }: SceneProps) => {
    const c = useTheme();
    const slide = tween(f, audioAt + 4, 40, easeInOut);
    const pct = (6 * slide).toFixed(1).replace(/\.0$/, '');
    const fillAt = (i: number): number => audioAt + 28 + i * 36;
    const fill = (i: number): number => tween(f, fillAt(i), 30, easeInOut);
    const shown = COSTS.reduce((n, r, i) => n + r.amount * fill(i), 0);
    return (
        <>
            <SampleTag f={f} at={audioAt} />
            <Card style={{ left: 110, top: 250, width: 560, height: 540, ...enter(f, 2, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 36, fontSize: 38, fontWeight: 600 }}>Aumento</div>
                <div style={{ position: 'absolute', left: 44, top: 96, fontSize: 150, fontWeight: 600, lineHeight: 1, letterSpacing: -4, color: c.primary }}>{`${pct} %`}</div>
                <div data-probe-ignore style={{ position: 'absolute', left: 44, right: 44, top: 290, height: 14, borderRadius: 99, background: c.track }}>
                    <div style={{ height: '100%', borderRadius: 99, background: c.primary, width: `${(6 * slide * 10)}%` }} />
                    <div style={{ position: 'absolute', left: `${6 * slide * 10}%`, top: -9, width: 32, height: 32, marginLeft: -16, borderRadius: 99, background: c.fg, boxShadow: '0 4px 14px rgba(0,0,0,.35)' }} />
                </div>
                <div style={{ position: 'absolute', left: 44, top: 336 }}>
                    <Label>Sobre el salario base de la plantilla</Label>
                </div>
                <Tag label="120 personas" style={{ position: 'absolute', left: 44, bottom: 44 }} />
            </Card>
            <Card style={{ left: 710, top: 250, width: 1100, height: 540, ...enter(f, 8, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 36, fontSize: 38, fontWeight: 600 }}>Costo del primer año</div>
                <div style={{ position: 'absolute', left: 44, top: 90 }}>
                    <Label>Lo que suma este aumento a la empresa</Label>
                </div>
            </Card>
            {COSTS.map((r, i) => (
                <div key={r.label} style={{ position: 'absolute', left: 754, top: RROW(i), width: 1012, height: 56, boxSizing: 'border-box', borderRadius: radius.control, background: c.muted, border: `1px solid ${c.border}`, ...enter(f, audioAt + 10 + i * stagger, { dur: dur.enter, y: 10 }) }}>
                    <div style={{ position: 'absolute', left: 22, top: 12, fontSize: 28, fontWeight: 500 }}>{r.label}</div>
                    <div style={{ position: 'absolute', left: 430, top: 22, width: 300, height: 12, borderRadius: 99, background: c.track }}>
                        <div style={{ height: '100%', borderRadius: 99, background: c.primary, width: `${(r.amount / 1_080_000) * 100 * fill(i)}%` }} />
                    </div>
                    <div style={{ position: 'absolute', right: 22, top: 9, fontSize: 32, fontWeight: 600 }}>{money(r.amount * fill(i))}</div>
                </div>
            ))}
            <div style={{ position: 'absolute', left: 754, top: 704, width: 1012, height: 64, boxSizing: 'border-box', borderRadius: radius.control, background: c.accent, border: `2px solid ${c.primary}`, display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '0 22px', opacity: tween(f, audioAt + 20, dur.enter) }}>
                <div style={{ fontSize: 30, fontWeight: 600 }}>Total</div>
                <div style={{ fontSize: 40, fontWeight: 600, color: c.primary }}>{money(shown)}</div>
            </div>
            {COSTS.map((r, i) => (
                <FlyDot key={r.label} f={f} start={fillAt(i) - 6} len={26} from={{ x: 300, y: 430 }} to={{ x: 1190, y: RROW(i) + 30 }} lift={60 + i * 12} size={16} />
            ))}
        </>
    );
};

// ───────────────────────── 2. La distancia, a la vista ─────────────────────────
// Dos propuestas (empresa 4.5 %, sindicato 8 %) y «La distancia a negociar»; «Probar un punto medio» agrega una tercera.
const PROPOSALS = [
    { name: 'Empresa', pct: '4.5 %', amount: 1_143_315 },
    { name: 'Sindicato', pct: '8 %', amount: 2_032_560 },
    { name: 'Punto medio', pct: '6.25 %', amount: 1_587_938 },
] as const;
const MAXW = 500;
const BAR_Y = (i: number): number => 250 + 124 + i * 88;

export const CompareScene = ({ f, audioAt }: SceneProps) => {
    const c = useTheme();
    const CLICK = audioAt + 128;
    const press = tween(f, CLICK - 3, 6) * (1 - tween(f, CLICK + 3, 6));
    const grow = (i: number): number => (i === 2 ? tween(f, CLICK + 8, 34, easeInOut) : tween(f, audioAt + 20 + i * 10, 40, easeInOut));
    const gap = Math.round((PROPOSALS[1].amount - PROPOSALS[0].amount) * tween(f, audioAt + 78, 40, (t) => t));
    return (
        <>
            <SampleTag f={f} at={audioAt} />
            <Card style={{ left: 110, top: 250, width: 1000, height: 540, ...enter(f, 2, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 36, fontSize: 38, fontWeight: 600 }}>Propuestas</div>
                <div style={{ position: 'absolute', left: 44, top: 88 }}>
                    <Label>Costo del primer año</Label>
                </div>
                <div style={{ position: 'absolute', left: 44, right: 44, bottom: 40, height: 96, borderRadius: radius.control, background: c.warnBg, border: `1px solid ${c.warn}`, boxSizing: 'border-box', display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '0 26px', opacity: tween(f, audioAt + 70, dur.enter) }}>
                    <div style={{ fontSize: 28, fontWeight: 600, color: c.warnFg }}>La distancia a negociar</div>
                    <div style={{ fontSize: 52, fontWeight: 600, color: c.warnFg }}>{money(gap)}</div>
                </div>
            </Card>
            {PROPOSALS.map((p, i) => {
                const g = grow(i);
                const w = (p.amount / 2_032_560) * MAXW * g;
                const vis = i === 2 ? tween(f, CLICK + 4, dur.enter) : 1;
                return (
                    <div key={p.name} style={{ position: 'absolute', left: 154, top: BAR_Y(i), width: 912, height: 76, opacity: vis, ...(i < 2 ? enter(f, audioAt + 8 + i * stagger, { dur: dur.enter, y: 10 }) : {}) }}>
                        <div style={{ position: 'absolute', left: 0, top: 8 }}>
                            <div style={{ fontSize: 30, fontWeight: 600 }}>{p.name}</div>
                            <div style={{ fontSize: 26, color: c.mutedFg }}>{p.pct}</div>
                        </div>
                        <div style={{ position: 'absolute', left: 250, top: 22, width: MAXW, height: 32, borderRadius: 8, background: c.track }}>
                            <div style={{ height: '100%', borderRadius: 8, width: w, background: i === 2 ? c.warn : c.primary, boxShadow: g > 0.95 ? `0 0 16px color-mix(in srgb, ${i === 2 ? c.warn : c.primary} 45%, transparent)` : 'none' }} />
                        </div>
                        <div style={{ position: 'absolute', left: 250 + MAXW + 20, top: 18, fontSize: 32, fontWeight: 600, whiteSpace: 'nowrap', opacity: g }}>{millions(p.amount * g)}</div>
                    </div>
                );
            })}
            <Card style={{ left: 1150, top: 250, width: 660, height: 540, ...enter(f, 8, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 36, fontSize: 24, fontWeight: 600, letterSpacing: 1.5, color: c.primary }}>LA DISTANCIA A NEGOCIAR</div>
                {[
                    ['1 punto a sindicalizados', '≈ $254,000 al año'],
                    ['1 mes de retroactivo', '≈ $169,000'],
                ].map(([k, v], i) => (
                    <div key={k} style={{ position: 'absolute', left: 44, right: 44, top: 96 + i * 130, height: 112, boxSizing: 'border-box', borderRadius: radius.control, background: c.muted, border: `1px solid ${c.border}`, padding: '18px 24px', ...enter(f, audioAt + 74 + i * 8, { dur: dur.enter, y: 10 }) }}>
                        <div style={{ fontSize: 26, color: c.mutedFg }}>{k}</div>
                        <div style={{ marginTop: 6, fontSize: 38, fontWeight: 600 }}>{v}</div>
                    </div>
                ))}
                <Button label="Probar un punto medio" pressed={press} style={{ position: 'absolute', left: 44, bottom: 40, ...pop(f, audioAt + 96) }} />
            </Card>
            <Cursor f={f} path={[{ at: audioAt + 104, x: 1700, y: 640 }, { at: CLICK - 2, x: 1350, y: 722 }]} clicks={[CLICK]} />
        </>
    );
};

// ───────────────────────── 3. El costo de cada año ─────────────────────────
// Proyección a cinco años con supuestos de plantilla y revisiones futuras (cifras de ejemplo).
const YEARS = [
    { year: '2026', amount: 1_524_400 },
    { year: '2027', amount: 1_620_000 },
    { year: '2028', amount: 1_710_000 },
    { year: '2029', amount: 1_820_000 },
    { year: '2030', amount: 1_930_000 },
] as const;
const YTOTAL = YEARS.reduce((n, y) => n + y.amount, 0); // 8,604,400
const BASE_Y = 250 + 438;

export const ProjectScene = ({ f, audioAt }: SceneProps) => {
    const c = useTheme();
    const col = (i: number): number => tween(f, audioAt + 14 + i * 12, 34, easeInOut);
    const total = YTOTAL * tween(f, audioAt + 20, 90, (t) => t);
    return (
        <>
            <SampleTag f={f} at={audioAt} />
            <Card style={{ left: 110, top: 250, width: 1100, height: 540, ...enter(f, 2, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 36, fontSize: 38, fontWeight: 600 }}>Costo adicional por año</div>
                <div style={{ position: 'absolute', left: 44, right: 44, top: 438, height: 2, background: c.border }} />
            </Card>
            {YEARS.map((y, i) => {
                const h = (y.amount / 1_930_000) * 290 * col(i);
                const x = 154 + i * 220;
                return (
                    <div key={y.year}>
                        <div style={{ position: 'absolute', left: x, top: BASE_Y - 48 - h, width: 130, textAlign: 'center', fontSize: 30, fontWeight: 600, opacity: col(i) }}>{millions(y.amount * col(i))}</div>
                        <div style={{ position: 'absolute', left: x, top: BASE_Y - h, width: 130, height: h, borderRadius: '10px 10px 4px 4px', background: i === 0 ? c.primary : `color-mix(in srgb, ${c.primary} ${70 - i * 6}%, ${c.track})`, boxShadow: col(i) > 0.95 ? `0 0 18px color-mix(in srgb, ${c.primary} 35%, transparent)` : 'none' }} />
                        <div style={{ position: 'absolute', left: x, top: BASE_Y + 12, width: 130, textAlign: 'center', fontSize: 26, color: c.mutedFg }}>{y.year}</div>
                    </div>
                );
            })}
            <Card style={{ left: 1250, top: 250, width: 560, height: 540, ...enter(f, 8, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 36, fontSize: 24, fontWeight: 600, letterSpacing: 1.5, color: c.primary }}>EN 5 AÑOS</div>
                <div style={{ position: 'absolute', left: 44, top: 82, fontSize: 84, fontWeight: 600, lineHeight: 1, letterSpacing: -2 }}>{millions(total)}</div>
                <div style={{ position: 'absolute', left: 44, top: 190 }}>
                    <Label>Lo que suma este aumento, año por año</Label>
                </div>
                {[
                    ['Plantilla', '120 personas'],
                    ['Revisión futura', '6 % anual'],
                ].map(([k, v], i) => (
                    <div key={k} style={{ position: 'absolute', left: 44, right: 44, top: 282 + i * 100, height: 84, boxSizing: 'border-box', borderRadius: radius.control, background: c.muted, border: `1px solid ${c.border}`, display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '0 22px', ...enter(f, audioAt + 64 + i * 10, { dur: dur.enter, y: 10 }) }}>
                        <div style={{ fontSize: 26, color: c.mutedFg }}>{k}</div>
                        <div style={{ fontSize: 30, fontWeight: 600 }}>{v}</div>
                    </div>
                ))}
            </Card>
        </>
    );
};

// ───────────────────────── 4. Un reporte para la mesa ─────────────────────────
// El reporte se comparte con un enlace temporal, de solo lectura, para alguien sin cuenta.
const PARTS = ['Resumen', 'Costo del primer año', 'Los próximos años', 'Supuestos'] as const;

export const ReportScene = ({ f, audioAt }: SceneProps) => {
    const c = useTheme();
    const CLICK = audioAt + 78;
    const press = tween(f, CLICK - 3, 6) * (1 - tween(f, CLICK + 3, 6));
    const link = tween(f, CLICK + 6, dur.enter, easeBack);
    const reader = tween(f, CLICK + 50, dur.enter, easeBack);
    return (
        <>
            <SampleTag f={f} at={audioAt} />
            <Card style={{ left: 110, top: 250, width: 860, height: 540, ...enter(f, 2, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 34, display: 'flex', alignItems: 'center', gap: 16, fontSize: 38, fontWeight: 600 }}>
                    <Icon name="file" size={42} color={c.primary} /> Reporte de la propuesta
                </div>
                {PARTS.map((p, i) => {
                    const on = tween(f, audioAt + 14 + i * 12, dur.micro + 4);
                    return (
                        <div key={p} style={{ position: 'absolute', left: 44, right: 44, top: 112 + i * 100, height: 88, boxSizing: 'border-box', borderRadius: radius.control, background: c.muted, border: `1px solid ${on > 0.5 ? c.primary : c.border}`, display: 'flex', alignItems: 'center', gap: 18, padding: '0 22px' }}>
                            <div style={{ width: 44, height: 44, borderRadius: 99, flexShrink: 0, background: `color-mix(in srgb, ${c.primary} ${on * 100}%, ${c.track})`, display: 'flex', alignItems: 'center', justifyContent: 'center', transform: `scale(${1 + 0.15 * Math.sin(on * Math.PI)})` }}>
                                <div style={{ opacity: on, display: 'flex' }}>
                                    <Icon name="check" size={26} color={c.primaryFg} stroke={3} />
                                </div>
                            </div>
                            <div style={{ fontSize: 30, fontWeight: 500 }}>{p}</div>
                        </div>
                    );
                })}
            </Card>
            <Card style={{ left: 1010, top: 250, width: 800, height: 540, ...enter(f, 8, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 36, fontSize: 38, fontWeight: 600 }}>Compartir</div>
                <div style={{ position: 'absolute', left: 44, right: 44, top: 92, fontSize: 26, lineHeight: 1.3, color: c.mutedFg }}>Un enlace temporal para que alguien sin cuenta lea el reporte.</div>
                <Button label="Crear enlace" pressed={press} style={{ position: 'absolute', left: 44, top: 176, ...pop(f, audioAt + 40) }} />
                <div style={{ position: 'absolute', left: 44, right: 44, top: 270, height: 84, boxSizing: 'border-box', borderRadius: radius.control, background: c.accent, border: `1px solid ${c.primary}`, display: 'flex', alignItems: 'center', gap: 14, padding: '0 22px', opacity: link, transform: `translateY(${(1 - link) * 14}px)` }}>
                    <Icon name="lock" size={30} color={c.primary} />
                    <div style={{ flex: 1, fontSize: 30, fontWeight: 600, letterSpacing: 2 }}>••••••••••</div>
                    <Tag label="Solo lectura" />
                    <Tag label="Temporal" />
                </div>
                <div style={{ position: 'absolute', left: 44, right: 44, bottom: 40, height: 96, boxSizing: 'border-box', borderRadius: radius.control, background: c.muted, border: `1px solid ${reader > 0.5 ? c.primary : c.border}`, display: 'flex', alignItems: 'center', gap: 18, padding: '0 24px', opacity: reader, transform: `scale(${lerp(0.96, 1, reader)})` }}>
                    <div style={{ width: 56, height: 56, borderRadius: 99, background: c.accent, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 22, fontWeight: 600 }}>SC</div>
                    <div style={{ flex: 1 }}>
                        <div style={{ fontSize: 30, fontWeight: 600 }}>Alguien sin cuenta</div>
                        <div style={{ fontSize: 24, color: c.mutedFg }}>Lee el reporte, no puede cambiarlo</div>
                    </div>
                    <CheckBadge size={44} />
                </div>
            </Card>
            {Array.from({ length: 6 }, (_, k) => (
                <FlyDot key={k} f={f} start={CLICK + 30 + k * 4} len={26} from={{ x: 1400, y: 560 }} to={{ x: 1100, y: 722 }} lift={60} size={16} />
            ))}
            <Cursor f={f} path={[{ at: audioAt + 48, x: 1650, y: 640 }, { at: CLICK - 2, x: 1130, y: 450 }]} clicks={[CLICK]} />
        </>
    );
};
