import type { CSSProperties, ReactNode } from 'react';
import { easeInOut, enter, lerp, tween } from '../../ease';
import { Logo, Reveal } from '../../kit/primitives';
import { useTheme, type Palette } from '../../kit/themes';
import { CheckBadge, Icon, Phone, Tag, cardStyle } from '../../kit/ui';
import type { SceneProps } from '../../kit/schema';
import { dur, radius, stagger } from '../../kit/tokens';

const Center = ({ children, style }: { children: ReactNode; style?: CSSProperties }) => (
    <div style={{ position: 'absolute', inset: 0, display: 'flex', alignItems: 'center', justifyContent: 'center', ...style }}>{children}</div>
);

// 1. La necesidad: una respuesta, y el miedo a que lleve nombre.
const OPTIONS = ['Siempre', 'Casi siempre', 'Algunas veces', 'Casi nunca', 'Nunca'] as const;
export const NeedScene = ({ f, audioAt }: SceneProps) => {
    const c = useTheme();
    const card = cardStyle(c);
    const pick = tween(f, audioAt + 40, dur.micro + 4);
    const gone = tween(f, audioAt + 74, dur.morph, easeInOut);
    return (
        <>
            <div style={{ ...card, left: 460, top: 240, width: 1000, height: 450, padding: 48, ...enter(f, 2, { dur: dur.scene }) }}>
                <Tag label="Guía de referencia II" />
                <div style={{ marginTop: 36, fontSize: 52, lineHeight: 1.2, fontWeight: 600 }}>Mi trabajo me exige hacer mucho esfuerzo físico</div>
                <div style={{ marginTop: 56, display: 'flex', gap: 14 }}>
                    {OPTIONS.map((o, i) => {
                        const on = i === 2;
                        return (
                            <div
                                key={o}
                                style={{
                                    flex: 1,
                                    height: 84,
                                    borderRadius: radius.control,
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    textAlign: 'center',
                                    fontSize: 26,
                                    fontWeight: 500,
                                    border: `1px solid ${on && pick > 0.5 ? c.primary : c.border}`,
                                    background: on ? `color-mix(in srgb, ${c.primary} ${pick * 100}%, ${c.muted})` : c.muted,
                                    color: on && pick > 0.5 ? c.primaryFg : c.fg,
                                    ...enter(f, audioAt + i * stagger, { dur: dur.enter, y: 8 }),
                                }}
                            >
                                {o}
                            </div>
                        );
                    })}
                </div>
            </div>
            <div
                style={{
                    position: 'absolute',
                    left: 1180,
                    top: 270,
                    height: 56,
                    padding: '0 22px',
                    display: 'flex',
                    alignItems: 'center',
                    gap: 14,
                    borderRadius: radius.control,
                    background: c.secondary,
                    border: `1px solid ${c.border}`,
                    fontSize: 32,
                    opacity: tween(f, audioAt + 56, dur.enter) * (1 - gone),
                    filter: `blur(${gone * 10}px)`,
                    transform: `translateY(${-gone * 14}px)`,
                }}
            >
                <Icon name="eyeOff" size={32} color={c.primary} />
                Mariana L.
            </div>
        </>
    );
};

// 2. El enlace: único por persona, de un solo uso, desde el celular.
export const LinkScene = ({ f, audioAt, audioDur }: SceneProps) => {
    const c = useTheme();
    const card = cardStyle(c);
    const used = tween(f, audioAt + audioDur - 40, dur.morph, easeInOut);
    return (
        <>
            <div style={{ ...card, left: 250, top: 320, width: 640, height: 240, padding: 40, ...enter(f, 2, { dur: dur.scene, x: -40, y: 0 }) }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 16, fontSize: 34, fontWeight: 600 }}>
                    <Icon name="mail" size={40} color={c.primary} /> Su invitación
                </div>
                <div style={{ marginTop: 22, fontSize: 28, color: c.mutedFg }}>Un enlace propio para esta persona.</div>
                <div style={{ marginTop: 34, display: 'flex', alignItems: 'center', gap: 16 }}>
                    <Tag label={used > 0.5 ? 'Enlace usado' : 'Un solo uso'} />
                    {used > 0.5 ? <CheckBadge size={42} style={{ transform: `scale(${tween(used, 0.5, 0.5)})` }} /> : <Icon name="lock" size={34} color={c.primary} />}
                </div>
            </div>
            <Reveal f={f} at={audioAt + 30} x={-20} y={0} style={{ position: 'absolute', left: 930, top: 408 }}>
                <Icon name="arrowRight" size={64} color={c.primary} />
            </Reveal>
            <Phone style={{ left: 1160, top: 90, ...enter(f, audioAt + 34, { dur: dur.scene, x: 50, y: 0 }) }}>
                <div style={{ position: 'absolute', left: 28, right: 28, top: 90 }}>
                    <div style={{ fontSize: 22, color: c.mutedFg }}>Guía de referencia II</div>
                    <div style={{ marginTop: 14, fontSize: 32, fontWeight: 600, lineHeight: 1.2 }}>Mi trabajo me exige hacer mucho esfuerzo físico</div>
                    {OPTIONS.map((o, i) => (
                        <div
                            key={o}
                            style={{
                                marginTop: i === 0 ? 34 : 10,
                                height: 58,
                                padding: '0 18px',
                                display: 'flex',
                                alignItems: 'center',
                                borderRadius: radius.control,
                                background: c.muted,
                                border: `1px solid ${c.border}`,
                                fontSize: 24,
                            }}
                        >
                            {o}
                        </div>
                    ))}
                </div>
            </Phone>
        </>
    );
};

const ROW = { h: 72, gap: 14 } as const;
const PANEL = { top: 240, h: 390 } as const;
const INVITES = [
    { name: 'Mariana L.', initials: 'ML', done: true },
    { name: 'Jorge R.', initials: 'JR', done: false },
    { name: 'Lucía H.', initials: 'LH', done: false },
] as const;
const rowStyle = (c: Palette): CSSProperties => ({ height: ROW.h, marginTop: ROW.gap, borderRadius: radius.control, background: c.muted, border: `1px solid ${c.border}`, display: 'flex', alignItems: 'center', gap: 16, padding: '0 20px', boxSizing: 'border-box' });

/** El punto sale de la invitación de Mariana y llega a la primera respuesta: nada lleva su nombre. */
const Traveler = ({ dot }: { dot: number }) => {
    const c = useTheme();
    return (
    <div style={{ position: 'absolute', left: lerp(720, 1170, dot) - 11, top: PANEL.top + 40 + 36 + 24 + ROW.h / 2 - 11 + 0, width: 22, height: 22, borderRadius: 99, background: c.primary, opacity: tween(dot, 0, 0.1) * (1 - tween(dot, 0.88, 0.12)) }} />
    );
};

// 3. La respuesta se guarda sin llave hacia la persona; la invitación solo marca «ya contestó».
export const SplitScene = ({ f, audioAt, audioDur }: SceneProps) => {
    const c = useTheme();
    const card = cardStyle(c);
    const send = audioAt + 36;
    const store = (i: number): number => tween(f, send + 40 + i * 14, dur.enter);
    const dot = tween(f, send, 44, easeInOut);
    const answered = tween(f, audioAt + audioDur - 70, dur.enter);
    const row = rowStyle(c);
    const panel: CSSProperties = { ...card, top: PANEL.top, width: 640, height: PANEL.h, padding: 40, overflow: 'hidden' };
    return (
        <>
            <div style={{ ...panel, left: 170, ...enter(f, 2, { dur: dur.scene, y: 0 }) }}>
                <div style={{ fontSize: 30, color: c.mutedFg }}>Invitaciones</div>
                {INVITES.map((p, i) => (
                    <div key={p.name} style={{ ...row, marginTop: i === 0 ? 24 : ROW.gap }}>
                        <div style={{ width: 44, height: 44, borderRadius: 99, background: c.accent, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 19, fontWeight: 600 }}>{p.initials}</div>
                        <div style={{ fontSize: 30, fontWeight: 500, flex: 1 }}>{p.name}</div>
                        {p.done ? (
                            <div style={{ opacity: answered }}>
                                <Tag label="Ya contestó" />
                            </div>
                        ) : (
                            <div style={{ fontSize: 26, color: c.mutedFg }}>Pendiente</div>
                        )}
                    </div>
                ))}
            </div>
            <div style={{ ...panel, left: 1110, ...enter(f, 8, { dur: dur.scene, y: 0 }) }}>
                <div style={{ fontSize: 30, color: c.mutedFg }}>Respuestas</div>
                {[0, 1, 2].map((i) => (
                    <div key={i} style={{ ...row, marginTop: i === 0 ? 24 : ROW.gap, opacity: store(i) }}>
                        <Icon name="listCheck" size={30} color={c.primary} />
                        <div style={{ fontSize: 28, color: c.mutedFg }}>Respuesta anónima</div>
                    </div>
                ))}
            </div>
            <div style={{ position: 'absolute', left: 959, top: PANEL.top - 10, height: PANEL.h + 20, borderLeft: `2px dashed ${c.border}`, ...enter(f, audioAt + 8, { dur: dur.scene, y: 0, scale: 1 }) }} />
            <div style={{ position: 'absolute', left: 0, right: 0, top: PANEL.top + PANEL.h + 30, display: 'flex', justifyContent: 'center' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 12, height: 56, padding: '0 22px', borderRadius: 999, background: c.accent, border: `1px solid ${c.border}`, fontSize: 30, fontWeight: 500, ...enter(f, audioAt + 14, { dur: dur.enter, y: 8 }) }}>
                    <Icon name="eyeOff" size={30} color={c.primary} /> Sin llave
                </div>
            </div>
            {dot > 0 && dot < 1 ? <Traveler dot={dot} /> : null}
        </>
    );
};

// 4. El mínimo por grupo: debajo, el grupo no se muestra.
const GROUPS = [
    { name: 'Producción', total: 64 },
    { name: 'Administración', total: 21 },
    { name: 'Almacén', total: 4 },
] as const;
export const GroupsScene = ({ f, audioAt, audioDur }: SceneProps) => {
    const c = useTheme();
    const card = cardStyle(c);
    const lockAt = audioAt + Math.round(audioDur * 0.55);
    return (
        <div style={{ position: 'absolute', left: 360, top: 170, width: 1200 }}>
            {GROUPS.map((g, i) => {
                const n = Math.round(g.total * tween(f, audioAt + 8 + i * stagger, dur.count, (t) => t));
                const locked = i === 2 ? tween(f, lockAt, dur.morph, easeInOut) : 0;
                return (
                    <div key={g.name} style={{ ...card, position: 'relative', marginBottom: 28, height: 190, padding: 36, ...enter(f, 2 + i * stagger, { dur: dur.scene, y: 16 }) }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline' }}>
                            <div style={{ fontSize: 40, fontWeight: 600 }}>{g.name}</div>
                            <div style={{ fontSize: 68, fontWeight: 600, lineHeight: 1, opacity: 1 - locked, filter: `blur(${locked * 8}px)` }}>
                                {n} <span style={{ fontSize: 30, fontWeight: 400, color: c.mutedFg }}>respuestas</span>
                            </div>
                        </div>
                        <div style={{ position: 'absolute', left: 36, right: 36, bottom: 36, height: 12, borderRadius: 99, background: c.track, opacity: 1 - locked }}>
                            <div style={{ height: '100%', borderRadius: 99, background: c.primary, transformOrigin: 'left', transform: `scaleX(${n / 64})` }} />
                        </div>
                        {i === 2 ? (
                            <div style={{ position: 'absolute', left: 36, bottom: 40, display: 'flex', alignItems: 'center', gap: 14, fontSize: 32, color: c.mutedFg, opacity: locked }}>
                                <Icon name="lock" size={34} color={c.primary} /> Oculto: menos de 5 respuestas
                            </div>
                        ) : null}
                    </div>
                );
            })}
            <Reveal f={f} at={lockAt + 16} style={{ display: 'flex', justifyContent: 'center' }}>
                <Tag label="Mínimo por grupo: 5, configurable" />
            </Reveal>
        </div>
    );
};

// 5. Beneficio y cierre.
export const CloseScene = ({ f, audioAt }: SceneProps) => {
    const c = useTheme();
    return (
    <Center style={{ flexDirection: 'column', gap: 36 }}>
        <Reveal f={f} at={audioAt} calm>
            <Logo height={88} />
        </Reveal>
        <Reveal f={f} at={audioAt + 20} calm style={{ fontSize: 64, fontWeight: 600, letterSpacing: -1 }}>
            Confianza para responder.
        </Reveal>
        <Reveal f={f} at={audioAt + 44} calm style={{ fontSize: 64, fontWeight: 600, letterSpacing: -1, color: c.primary }}>
            Datos para decidir.
        </Reveal>
    </Center>
    );
};
