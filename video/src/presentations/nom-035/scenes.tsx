import { easeInOut, enter, lerp, tween } from '../../ease';
import { Reveal } from '../../kit/primitives';
import type { SceneProps } from '../../kit/schema';
import { useTheme } from '../../kit/themes';
import { dur, radius, stagger } from '../../kit/tokens';
import { Button, Card, CheckBadge, Icon, Meter, Tag } from '../../kit/ui';

const Label = ({ children }: { children: string }) => {
    const c = useTheme();
    return <div style={{ fontSize: 30, color: c.mutedFg }}>{children}</div>;
};

// 1. Guías: el tamaño del centro decide qué guías aplican (num. 4.4: ≤15 → I; 16–50 → I y II; >50 → I y III).
const BANDS = [
    { range: 'Hasta 15 personas', guides: 'Guía I' },
    { range: 'De 16 a 50 personas', guides: 'Guías I y II' },
    { range: 'Más de 50 personas', guides: 'Guías I y III' },
] as const;

export const GuidesScene = ({ f, audioAt }: SceneProps) => {
    const c = useTheme();
    const pick = tween(f, audioAt + 44, dur.morph, easeInOut);
    return (
        <>
            <Card style={{ left: 110, top: 150, width: 1700, height: 130, padding: '0 44px', display: 'flex', alignItems: 'center', justifyContent: 'space-between', ...enter(f, 2, { dur: dur.scene, y: 0 }) }}>
                <div>
                    <div style={{ fontSize: 44, fontWeight: 600 }}>Oficinas Monterrey</div>
                    <div style={{ marginTop: 6, fontSize: 28, color: c.mutedFg }}>Administración y ventas</div>
                </div>
                <div style={{ textAlign: 'right' }}>
                    <Label>Personal</Label>
                    <div style={{ fontSize: 44, fontWeight: 600 }}>60 personas</div>
                </div>
            </Card>
            {BANDS.map((b, i) => {
                const on = i === 2;
                return (
                    <Card
                        key={b.range}
                        style={{
                            left: 110 + i * 580,
                            top: 330,
                            width: 540,
                            height: 360,
                            padding: 44,
                            border: `2px solid ${on ? `color-mix(in srgb, ${c.primary} ${pick * 100}%, ${c.border})` : c.border}`,
                            background: on ? `color-mix(in srgb, ${c.accent} ${pick * 100}%, ${c.card})` : c.card,
                            ...enter(f, audioAt + 4 + i * stagger * 2, { dur: dur.enter, y: 14 }),
                        }}
                    >
                        <Label>{b.range}</Label>
                        <div style={{ marginTop: 26, fontSize: 72, fontWeight: 600, letterSpacing: -1 }}>{b.guides}</div>
                        {on ? (
                            <div style={{ position: 'absolute', left: 44, bottom: 44, display: 'flex', alignItems: 'center', gap: 16, opacity: pick }}>
                                <CheckBadge size={52} />
                                <div style={{ fontSize: 32, fontWeight: 500 }}>Le toca a este centro</div>
                            </div>
                        ) : (
                            <div style={{ position: 'absolute', left: 44, bottom: 44, fontSize: 28, color: c.mutedFg, opacity: 1 - pick * 0.4 }}>Ningún otro centro</div>
                        )}
                    </Card>
                );
            })}
            <Reveal f={f} at={audioAt + 20} style={{ position: 'absolute', left: 110, top: 722 }}>
                <div style={{ fontSize: 28, color: c.mutedFg }}>Numeral 4.4 de la NOM-035: la guía se elige por centro de trabajo, no por empresa.</div>
            </Reveal>
        </>
    );
};

// 2. Muestra: n = 0.9604·N / (0.0025·(N−1) + 0.9604); N = 60 → n = 52. 52 puntos se marcan, 8 quedan fuera.
const OUT = new Set([3, 11, 17, 26, 34, 41, 49, 56]);
export const SampleScene = ({ f, audioAt }: SceneProps) => {
    const c = useTheme();
    const start = audioAt + 30;
    const COUNT = 60;
    const chosen = (i: number): number => {
        const rank = i - [...OUT].filter((o) => o < i).length;
        return OUT.has(i) ? 0 : tween(f, start + rank * 1.1, 10);
    };
    const n = Math.round(52 * tween(f, start, 58, (t) => t));
    return (
        <>
            <Card style={{ left: 110, top: 170, width: 600, height: 610, padding: 44, ...enter(f, 2, { dur: dur.scene, y: 0 }) }}>
                <div style={{ fontSize: 40, fontWeight: 600 }}>Oficinas Monterrey</div>
                <div style={{ marginTop: 30 }}>
                    <Label>Personal</Label>
                    <div style={{ fontSize: 56, fontWeight: 600 }}>60 personas</div>
                </div>
                <div style={{ marginTop: 34 }}>
                    <Label>Muestra para la Guía III</Label>
                    <div style={{ fontSize: 120, fontWeight: 600, lineHeight: 1, letterSpacing: -2, color: c.primary }}>{n}</div>
                </div>
                <Tag label="Proporcional por género" style={{ position: 'absolute', left: 44, bottom: 44 }} />
            </Card>
            <Card style={{ left: 760, top: 170, width: 1050, height: 610, padding: 44, ...enter(f, 8, { dur: dur.scene, y: 0 }) }}>
                <div style={{ fontSize: 40, fontWeight: 600 }}>Selección aleatoria</div>
                <div style={{ marginTop: 6 }}>
                    <Label>Se marcan las personas de la muestra</Label>
                </div>
                <div style={{ marginTop: 40, display: 'grid', gridTemplateColumns: 'repeat(10, 1fr)', rowGap: 26, justifyItems: 'center' }}>
                    {Array.from({ length: COUNT }, (_, i) => {
                        const t = chosen(i);
                        return <div key={i} style={{ width: 44, height: 44, borderRadius: 99, background: `color-mix(in srgb, ${c.primary} ${t * 100}%, ${c.track})`, transform: `scale(${lerp(1, 1.12, Math.sin(t * Math.PI))})` }} />;
                    })}
                </div>
            </Card>
        </>
    );
};

// 3. Invitaciones: cuántas contestaron, nunca qué contestaron; un recordatorio a quien falta.
const PEOPLE = [
    { name: 'Alexandra Olivas', initials: 'AO', area: 'Ventas' },
    { name: 'Alma Nava', initials: 'AN', area: 'Ventas' },
    { name: 'Blanca Osorio', initials: 'BO', area: 'Ventas' },
    { name: 'Diana Ulloa', initials: 'DU', area: 'Administración' },
] as const;
export const InvitesScene = ({ f, audioAt, audioDur }: SceneProps) => {
    const c = useTheme();
    const meter = tween(f, audioAt + 10, 50);
    const press = tween(f, audioAt + 76, 8);
    const sentAt = audioAt + 86;
    return (
        <>
            <Card style={{ left: 110, top: 170, width: 640, height: 600, padding: 44, ...enter(f, 2, { dur: dur.scene, y: 0 }) }}>
                <Label>Avance de Oficinas Monterrey</Label>
                <div style={{ marginTop: 10, display: 'flex', justifyContent: 'center' }}>
                    <Meter value={30 * meter} size={330}>
                        <div style={{ fontSize: 88, fontWeight: 600, lineHeight: 1 }}>{Math.round(18 * meter)}</div>
                        <div style={{ fontSize: 26, lineHeight: 1.2, color: c.mutedFg, textAlign: 'center' }}>de 60<br />contestaron</div>
                    </Meter>
                </div>
                <div style={{ marginTop: 30, fontSize: 28, lineHeight: 1.35, color: c.mutedFg }}>Aquí ves cuántas personas contestaron, nunca qué contestaron.</div>
            </Card>
            <Card style={{ left: 800, top: 170, width: 1010, height: 600, padding: 44, ...enter(f, 8, { dur: dur.scene, y: 0 }) }}>
                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                    <div style={{ fontSize: 40, fontWeight: 600 }}>
                        Pendientes <span style={{ color: c.mutedFg, fontWeight: 400 }}>· 42</span>
                    </div>
                    <Button label="Recordar a 42" pressed={press * (1 - tween(f, sentAt, 6))} />
                </div>
                {PEOPLE.map((p, i) => {
                    const sent = tween(f, sentAt + 4 + i * stagger, dur.enter);
                    return (
                        <div key={p.name} style={{ marginTop: i === 0 ? 34 : 14, height: 86, borderRadius: radius.control, background: c.muted, border: `1px solid ${c.border}`, display: 'flex', alignItems: 'center', gap: 20, padding: '0 24px', boxSizing: 'border-box', ...enter(f, audioAt + 20 + i * stagger, { dur: dur.enter, y: 10 }) }}>
                            <div style={{ width: 52, height: 52, borderRadius: 99, background: c.accent, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 22, fontWeight: 600 }}>{p.initials}</div>
                            <div style={{ flex: 1 }}>
                                <div style={{ fontSize: 30, fontWeight: 500 }}>{p.name}</div>
                                <div style={{ fontSize: 24, color: c.mutedFg }}>{p.area}</div>
                            </div>
                            <div style={{ position: 'relative', width: 330, height: 40 }}>
                                <div style={{ position: 'absolute', right: 0, fontSize: 26, color: c.mutedFg, opacity: 1 - sent }}>Pendiente</div>
                                <div style={{ position: 'absolute', right: 0, display: 'flex', alignItems: 'center', gap: 10, fontSize: 26, fontWeight: 500, whiteSpace: 'nowrap', opacity: sent }}>
                                    <Icon name="mail" size={28} color={c.primary} /> Recordatorio enviado
                                </div>
                            </div>
                        </div>
                    );
                })}
                <div style={{ marginTop: 22, fontSize: 26, color: c.mutedFg, opacity: tween(f, audioAt + audioDur - 40, dur.enter) }}>Y 38 personas más.</div>
            </Card>
        </>
    );
};

// 4. Informe del numeral 7.7: secciones que exige la norma; la Guía I solo para personas autorizadas.
const SECTIONS = ['Datos del centro', 'Objetivo', 'Actividades', 'Método', 'Resultados', 'Conclusiones', 'Acciones de intervención', 'Responsable'] as const;
export const ReportScene = ({ f, audioAt }: SceneProps) => {
    const c = useTheme();
    return (
        <>
            <Card style={{ left: 110, top: 170, width: 1000, height: 600, padding: 44, ...enter(f, 2, { dur: dur.scene, y: 0 }) }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 18 }}>
                    <Icon name="file" size={44} color={c.primary} />
                    <div style={{ fontSize: 42, fontWeight: 600 }}>Informe · numeral 7.7</div>
                </div>
                <div style={{ marginTop: 40, display: 'grid', gridTemplateColumns: '1fr 1fr', columnGap: 36, rowGap: 22 }}>
                    {SECTIONS.map((s, i) => {
                        const on = tween(f, audioAt + 20 + i * 9, dur.micro + 4);
                        return (
                            <div key={s} style={{ height: 88, borderRadius: radius.control, background: c.muted, border: `1px solid ${c.border}`, display: 'flex', alignItems: 'center', gap: 16, padding: '0 20px', boxSizing: 'border-box' }}>
                                <div style={{ width: 40, height: 40, borderRadius: 99, flexShrink: 0, background: `color-mix(in srgb, ${c.primary} ${on * 100}%, ${c.track})`, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
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
            <Card style={{ left: 1160, top: 170, width: 650, height: 600, padding: 44, ...enter(f, 8, { dur: dur.scene, y: 0 }) }}>
                <div style={{ width: 84, height: 84, borderRadius: radius.card, background: c.accent, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                    <Icon name="lock" size={46} color={c.primary} />
                </div>
                <div style={{ marginTop: 34, fontSize: 42, fontWeight: 600, lineHeight: 1.15 }}>Canalización de la Guía I</div>
                <div style={{ marginTop: 18, fontSize: 30, lineHeight: 1.35, color: c.mutedFg }}>Solo la ven las personas autorizadas. El informe nunca lleva respuestas individuales.</div>
                <Reveal f={f} at={audioAt + 70} style={{ position: 'absolute', left: 44, bottom: 44 }}>
                    <Tag label="Evidencia para la STPS" />
                </Reveal>
            </Card>
        </>
    );
};
