import type { CSSProperties } from 'react';
import { easeInOut, enter, lerp, tween } from '../../ease';
import { Cursor, FlyDot, easeBack, pop, type Pt } from '../../kit/fx';
import type { SceneProps } from '../../kit/schema';
import { useTheme } from '../../kit/themes';
import { dur, radius, stagger } from '../../kit/tokens';
import { QrCode } from '../../kit/qr';
import { Button, Card, CheckBadge, Icon, Phone } from '../../kit/ui';

const Label = ({ children }: { children: string }) => {
    const c = useTheme();
    return <div style={{ fontSize: 26, color: c.mutedFg }}>{children}</div>;
};

const REFERENCE = 'K7M2 9QXD'; // de demostración: 8 caracteres en grupos de cuatro

// ───────────────────────── 1. Escanea y presenta tu queja ─────────────────────────
// Cartel imprimible con QR por empresa; la queja es anónima por omisión; al enviar se da una referencia y una clave (una sola vez).
const PHONE = { left: 800, top: 236, w: 360, h: 574 } as const;
const IDENTITIES = [
    { label: 'Anónima', text: 'No da su nombre. Le responderemos con su referencia y su clave.' },
    { label: 'Confidencial', text: 'Da su nombre. Solo lo ven quienes atienden su queja, nunca la persona de la que se trata.' },
] as const;

export const ScanScene = ({ f, audioAt }: SceneProps) => {
    const c = useTheme();
    const beam = tween(f, audioAt + 14, 40, easeInOut);
    const sweep = Math.abs(Math.sin(beam * Math.PI * 1.5)) * (beam > 0 && beam < 1 ? 1 : 0);
    const form = tween(f, audioAt + 50, dur.enter);
    const pick = tween(f, audioAt + 112, dur.micro + 4);
    const sent = tween(f, audioAt + 156, dur.morph, easeInOut);
    const QRBOX = { x: 110 + 44, y: 250 + 296, s: 220 };
    return (
        <>
            <Card style={{ left: 110, top: 250, width: 600, height: 540, ...enter(f, 2, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 34, fontSize: 24, fontWeight: 600, letterSpacing: 1.5, color: c.primary }}>QUEJAS Y DENUNCIAS</div>
                <div style={{ position: 'absolute', left: 44, right: 44, top: 74, fontSize: 40, fontWeight: 600, lineHeight: 1.12, letterSpacing: -0.5 }}>¿Algo no está bien en su trabajo?</div>
                <div style={{ position: 'absolute', left: 44, right: 44, top: 196, fontSize: 26, lineHeight: 1.3, color: c.mutedFg }}>Cuéntelo de forma anónima o confidencial.</div>
                <div style={{ position: 'absolute', left: 44, top: 296, width: 220, height: 220, boxSizing: 'border-box', padding: 10, borderRadius: radius.card, border: `3px solid ${c.fg}`, background: c.card }}>
                    <QrCode size={192} />
                </div>
                <div style={{ position: 'absolute', left: 290, top: 336, width: 270, fontSize: 28, fontWeight: 600, lineHeight: 1.2 }}>Escanee con su celular</div>
            </Card>
            {beam > 0 && beam < 1 ? <div style={{ position: 'absolute', left: QRBOX.x, top: QRBOX.y + 10 + sweep * (QRBOX.s - 24), width: QRBOX.s, height: 4, borderRadius: 4, background: c.primary, boxShadow: `0 0 22px ${c.primary}` }} /> : null}
            <Phone style={{ left: PHONE.left, top: PHONE.top, width: PHONE.w, height: PHONE.h, ...enter(f, 12, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 26, right: 26, top: 66, opacity: form * (1 - sent) }}>
                    <div style={{ fontSize: 24, color: c.mutedFg }}>Quejas y denuncias</div>
                    <div style={{ marginTop: 10, fontSize: 30, fontWeight: 600, lineHeight: 1.2 }}>¿Cómo quiere presentarla?</div>
                    {IDENTITIES.map((o, i) => {
                        const on = i === 0 ? pick : 0;
                        return (
                            <div key={o.label} style={{ marginTop: i === 0 ? 24 : 12, height: 74, borderRadius: radius.control, boxSizing: 'border-box', border: `${on > 0.5 ? 2 : 1}px solid ${on > 0.5 ? c.primary : c.border}`, background: `color-mix(in srgb, ${c.accent} ${on * 100}%, ${c.muted})`, display: 'flex', alignItems: 'center', gap: 14, padding: '0 18px' }}>
                                <div style={{ width: 28, height: 28, borderRadius: 99, flexShrink: 0, border: `2px solid ${on > 0.5 ? c.primary : c.mutedFg}`, background: `color-mix(in srgb, ${c.primary} ${on * 100}%, transparent)` }} />
                                <div style={{ fontSize: 28, fontWeight: 600 }}>{o.label}</div>
                            </div>
                        );
                    })}
                </div>
                <div style={{ position: 'absolute', left: 26, right: 26, top: 110, opacity: sent, textAlign: 'center' }}>
                    <CheckBadge size={84} style={{ margin: '0 auto', transform: `scale(${lerp(0.7, 1, sent)})` }} />
                    <div style={{ marginTop: 22, fontSize: 30, fontWeight: 600 }}>Queja enviada</div>
                    <div style={{ marginTop: 16, fontSize: 24, color: c.mutedFg }}>Su referencia</div>
                    <div style={{ marginTop: 6, fontSize: 38, fontWeight: 600, letterSpacing: 2, color: c.primary }}>{REFERENCE}</div>
                </div>
            </Phone>
            <Card style={{ left: 1220, top: 250, width: 590, height: 540, ...enter(f, 20, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 34, fontSize: 36, fontWeight: 600 }}>Cómo se presenta</div>
                {IDENTITIES.map((o, i) => {
                    const on = i === 0 ? pick : 0;
                    return (
                        <div key={o.label} style={{ position: 'absolute', left: 44, right: 44, top: 98 + i * 182, height: 168, boxSizing: 'border-box', borderRadius: radius.control, border: `${on > 0.5 ? 2 : 1}px solid ${on > 0.5 ? c.primary : c.border}`, background: `color-mix(in srgb, ${c.accent} ${on * 100}%, ${c.muted})`, padding: '20px 24px' }}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: 12, fontSize: 30, fontWeight: 600 }}>
                                {o.label}
                                {on > 0.5 ? <CheckBadge size={30} style={pop(f, audioAt + 112)} /> : null}
                            </div>
                            <div style={{ marginTop: 8, fontSize: 24, lineHeight: 1.3, color: c.mutedFg }}>{o.text}</div>
                        </div>
                    );
                })}
                <div style={{ position: 'absolute', left: 44, right: 44, bottom: 22, display: 'flex', alignItems: 'center', gap: 12, fontSize: 24, color: c.mutedFg, ...pop(f, audioAt + 176) }}>
                    <Icon name="lock" size={28} color={c.primary} /> La clave se muestra una sola vez.
                </div>
            </Card>
            {Array.from({ length: 10 }, (_, k) => (
                <FlyDot key={k} f={f} start={audioAt + 44 + k * 3} len={26} from={{ x: QRBOX.x + 110, y: QRBOX.y + 110 }} to={{ x: PHONE.left + 180, y: PHONE.top + 300 }} lift={60} size={16} />
            ))}
        </>
    );
};

// ───────────────────────── 2. Llega a quien le toca ─────────────────────────
// Enrutamiento por conflicto de interés: quien es señalada no ve el caso; lo recibe otra persona encargada.
const HANDLERS = [
    { name: 'Laura M.', role: 'Recursos Humanos', initials: 'LM', state: 'conflict' },
    { name: 'Carlos P.', role: 'Dirección', initials: 'CP', state: 'receives' },
    { name: 'Elena V.', role: 'Persona externa', initials: 'EV', state: 'available' },
] as const;
const HROW = (i: number): number => 250 + 118 + i * 124;

export const RouteScene = ({ f, audioAt }: SceneProps) => {
    const c = useTheme();
    const lock = tween(f, audioAt + 70, dur.morph, easeBack);
    const recv = tween(f, audioAt + 96, dur.enter, easeBack);
    return (
        <>
            <Card style={{ left: 110, top: 250, width: 700, height: 540, ...enter(f, 2, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 36, display: 'flex', alignItems: 'center', gap: 14, fontSize: 38, fontWeight: 600 }}>
                    <Icon name="mail" size={40} color={c.primary} /> Queja recibida
                </div>
                <div style={{ position: 'absolute', left: 44, top: 110 }}>
                    <Label>Tipo</Label>
                    <div style={{ marginTop: 6, fontSize: 34, fontWeight: 600 }}>Violencia laboral</div>
                </div>
                <div style={{ position: 'absolute', left: 44, top: 238 }}>
                    <Label>Persona señalada</Label>
                    <div style={{ marginTop: 10, display: 'inline-flex', alignItems: 'center', height: 54, padding: '0 20px', borderRadius: radius.control, background: c.warnBg, color: c.warnFg, fontSize: 30, fontWeight: 600 }}>Recursos Humanos</div>
                </div>
                <div style={{ position: 'absolute', left: 44, right: 44, bottom: 40, fontSize: 26, lineHeight: 1.3, color: c.mutedFg }}>La plataforma revisa el conflicto antes de asignar el caso.</div>
            </Card>
            <Card style={{ left: 850, top: 250, width: 960, height: 540, ...enter(f, 8, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 36, fontSize: 38, fontWeight: 600 }}>Quién atiende</div>
            </Card>
            {HANDLERS.map((h, i) => {
                const conflict = h.state === 'conflict';
                const gets = h.state === 'receives';
                const hl = gets ? recv : 0;
                return (
                    <div key={h.name} style={{ position: 'absolute', left: 894, top: HROW(i), width: 872, height: 108, boxSizing: 'border-box', borderRadius: radius.control, background: `color-mix(in srgb, ${c.accent} ${hl * 100}%, ${c.muted})`, border: `${hl > 0.5 ? 2 : 1}px solid ${hl > 0.5 ? c.primary : c.border}`, display: 'flex', alignItems: 'center', gap: 22, padding: '0 28px', opacity: conflict ? lerp(1, 0.55, lock) : 1, ...enter(f, audioAt + 8 + i * stagger, { dur: dur.enter, y: 12 }) }}>
                        <div style={{ width: 60, height: 60, borderRadius: 99, background: c.accent, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 24, fontWeight: 600 }}>{h.initials}</div>
                        <div style={{ flex: 1 }}>
                            <div style={{ fontSize: 34, fontWeight: 600 }}>{h.name}</div>
                            <div style={{ fontSize: 26, color: c.mutedFg }}>{h.role}</div>
                        </div>
                        {conflict ? (
                            <div style={{ display: 'flex', alignItems: 'center', gap: 12, fontSize: 28, fontWeight: 500, color: c.warnFg, opacity: lock, transform: `scale(${lerp(0.8, 1, lock)})` }}>
                                <Icon name="lock" size={32} color={c.warn} /> En conflicto: no la ve
                            </div>
                        ) : null}
                        {gets ? (
                            <div style={{ display: 'flex', alignItems: 'center', gap: 12, fontSize: 28, fontWeight: 600, opacity: recv, transform: `scale(${lerp(0.8, 1, recv)})` }}>
                                <CheckBadge size={40} /> Recibe el caso
                            </div>
                        ) : null}
                        {h.state === 'available' ? <div style={{ fontSize: 26, color: c.mutedFg }}>Disponible</div> : null}
                    </div>
                );
            })}
            {Array.from({ length: 8 }, (_, k) => (
                <FlyDot key={`c${k}`} f={f} start={audioAt + 34 + k * 3} len={28} from={{ x: 700, y: 520 }} to={{ x: 1000, y: HROW(1) + 54 }} lift={50 + k * 4} size={16} />
            ))}
            {Array.from({ length: 3 }, (_, k) => (
                <FlyDot key={`b${k}`} f={f} start={audioAt + 34 + k * 5} len={26} from={{ x: 700, y: 520 }} to={{ x: 1000, y: HROW(0) + 54 }} lift={90} size={16} color={c.warn} />
            ))}
        </>
    );
};

// ───────────────────────── 3. Avisos sin contenido ─────────────────────────
// El aviso a quien atiende lleva conteos, nunca el texto de la queja ni nombres.
const INCLUDES = [
    { text: 'Conteo de casos nuevos', yes: true },
    { text: 'Plazos por vencer', yes: true },
    { text: 'Texto de la queja', yes: false },
    { text: 'Nombres de las personas', yes: false },
] as const;

export const NoticeScene = ({ f, audioAt }: SceneProps) => {
    const c = useTheme();
    const count = (to: number, at: number): number => Math.round(to * tween(f, audioAt + at, 36, (t) => t));
    const row = (i: number): number => audioAt + 70 + i * 16;
    const bar: CSSProperties = { height: 16, borderRadius: 99, background: c.track };
    return (
        <>
            <Card style={{ left: 110, top: 250, width: 1000, height: 540, ...enter(f, 2, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 34, display: 'flex', alignItems: 'center', gap: 14, fontSize: 26, color: c.mutedFg }}>
                    <Icon name="mail" size={30} color={c.primary} /> Buzón de quejas
                </div>
                <div style={{ position: 'absolute', left: 44, top: 82, fontSize: 44, fontWeight: 600, letterSpacing: -0.6 }}>Tienes casos por atender</div>
                <div style={{ position: 'absolute', left: 44, top: 170, display: 'flex', gap: 28 }}>
                    <div style={{ width: 280, height: 150, borderRadius: radius.card, background: c.muted, border: `1px solid ${c.border}`, boxSizing: 'border-box', padding: '20px 26px' }}>
                        <div style={{ fontSize: 80, fontWeight: 600, lineHeight: 1, color: c.primary }}>{count(2, 14)}</div>
                        <div style={{ marginTop: 6, fontSize: 28, color: c.mutedFg }}>casos nuevos</div>
                    </div>
                    <div style={{ width: 280, height: 150, borderRadius: radius.card, background: c.warnBg, border: `1px solid ${c.warn}`, boxSizing: 'border-box', padding: '20px 26px' }}>
                        <div style={{ fontSize: 80, fontWeight: 600, lineHeight: 1, color: c.warnFg }}>{count(1, 24)}</div>
                        <div style={{ marginTop: 6, fontSize: 28, color: c.warnFg }}>plazo por vencer</div>
                    </div>
                </div>
                <div style={{ position: 'absolute', left: 44, right: 44, top: 356, height: 140, borderRadius: radius.card, border: `2px dashed ${c.border}`, boxSizing: 'border-box', padding: '22px 26px', opacity: tween(f, audioAt + 50, dur.enter) }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 12, fontSize: 28, fontWeight: 600 }}>
                        <Icon name="eyeOff" size={32} color={c.primary} /> Contenido de la queja: nunca va en el correo
                    </div>
                    <div style={{ marginTop: 18, display: 'flex', flexDirection: 'column', gap: 10 }}>
                        <div style={{ ...bar, width: '92%' }} />
                        <div style={{ ...bar, width: '64%' }} />
                    </div>
                </div>
            </Card>
            <Card style={{ left: 1150, top: 250, width: 660, height: 540, ...enter(f, 8, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 34, fontSize: 38, fontWeight: 600 }}>Qué lleva el aviso</div>
                {INCLUDES.map((it, i) => {
                    const on = tween(f, row(i), dur.enter, easeBack);
                    return (
                        <div key={it.text} style={{ position: 'absolute', left: 44, right: 44, top: 110 + i * 100, height: 84, boxSizing: 'border-box', borderRadius: radius.control, background: c.muted, border: `1px solid ${it.yes && on > 0.5 ? c.primary : c.border}`, display: 'flex', alignItems: 'center', gap: 18, padding: '0 22px', opacity: lerp(0.4, 1, on) }}>
                            {it.yes ? <CheckBadge size={40} style={{ transform: `scale(${lerp(0.6, 1, on)})` }} /> : (
                                <div style={{ width: 40, height: 40, borderRadius: 99, display: 'flex', alignItems: 'center', justifyContent: 'center', background: c.track, transform: `scale(${lerp(0.6, 1, on)})` }}>
                                    <Icon name="eyeOff" size={24} color={c.mutedFg} />
                                </div>
                            )}
                            <div style={{ fontSize: 28, fontWeight: 500, color: it.yes ? c.fg : c.mutedFg, textDecoration: it.yes ? 'none' : 'line-through' }}>{it.text}</div>
                        </div>
                    );
                })}
            </Card>
        </>
    );
};

// ───────────────────────── 4. Seguimiento con referencia ─────────────────────────
// Recibida → Acuse enviado → En revisión → Cerrada; la persona sigue su caso con referencia y clave, sin cuenta.
const STEPS = ['Recibida', 'Acuse enviado', 'En revisión', 'Cerrada'] as const;

export const FollowScene = ({ f, audioAt }: SceneProps) => {
    const c = useTheme();
    const CLICK = audioAt + 62;
    const press = tween(f, CLICK - 3, 6) * (1 - tween(f, CLICK + 3, 6));
    const done = (i: number): number => (i < 2 ? tween(f, audioAt + 14 + i * 10, dur.enter) : i === 2 ? tween(f, CLICK + 4, dur.enter, easeBack) : 0);
    const msg = tween(f, CLICK + 40, dur.enter, easeBack);
    const phoneDone = (i: number): number => (i < 2 ? done(i) : i === 2 ? tween(f, CLICK + 38, dur.enter, easeBack) : 0);
    return (
        <>
            <Card style={{ left: 110, top: 250, width: 1060, height: 540, ...enter(f, 2, { dur: dur.scene, y: 18 }) }}>
                <div style={{ position: 'absolute', left: 44, top: 34, fontSize: 40, fontWeight: 600 }}>{`Caso ${REFERENCE}`}</div>
                <div style={{ position: 'absolute', left: 44, top: 92 }}>
                    <Label>Violencia laboral · vista de quien lo atiende</Label>
                </div>
                <div style={{ position: 'absolute', left: 44, right: 44, top: 168, display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 18 }}>
                    {STEPS.map((s, i) => {
                        const d = done(i);
                        return (
                            <div key={s}>
                                <div style={{ height: 10, borderRadius: 99, background: c.track }}>
                                    <div style={{ height: '100%', borderRadius: 99, background: c.primary, width: `${d * 100}%`, boxShadow: d > 0.9 ? `0 0 14px color-mix(in srgb, ${c.primary} 50%, transparent)` : 'none' }} />
                                </div>
                                <div style={{ marginTop: 12, fontSize: 27, fontWeight: d > 0.5 ? 600 : 500, color: d > 0.5 ? c.fg : c.mutedFg }}>{s}</div>
                            </div>
                        );
                    })}
                </div>
                <div style={{ position: 'absolute', left: 44, right: 44, top: 292, height: 120, borderRadius: radius.control, background: c.muted, border: `1px solid ${c.border}`, boxSizing: 'border-box', padding: '16px 24px' }}>
                    <div style={{ fontSize: 24, color: c.mutedFg }}>Mensaje a la persona</div>
                    <div style={{ marginTop: 6, fontSize: 28, fontWeight: 500, opacity: tween(f, CLICK + 16, dur.enter) }}>Recibimos su queja y la estamos revisando.</div>
                </div>
                <Button label="Pasar a revisión" pressed={press} style={{ position: 'absolute', left: 44, bottom: 40 }} />
            </Card>
            <Phone style={{ left: 1300, top: 236, width: 360, height: 574, ...enter(f, 12, { dur: dur.scene, x: 30, y: 0 }) }}>
                <div style={{ position: 'absolute', left: 26, right: 26, top: 66 }}>
                    <div style={{ fontSize: 24, color: c.mutedFg }}>Seguimiento</div>
                    <div style={{ marginTop: 6, fontSize: 36, fontWeight: 600, letterSpacing: 2, color: c.primary }}>{REFERENCE}</div>
                    {STEPS.map((s, i) => {
                        const d = phoneDone(i);
                        return (
                            <div key={s} style={{ marginTop: i === 0 ? 22 : 8, height: 44, display: 'flex', alignItems: 'center', gap: 14 }}>
                                <div style={{ width: 30, height: 30, borderRadius: 99, flexShrink: 0, background: `color-mix(in srgb, ${c.primary} ${d * 100}%, ${c.track})`, display: 'flex', alignItems: 'center', justifyContent: 'center', transform: `scale(${1 + 0.15 * Math.sin(d * Math.PI)})` }}>
                                    <div style={{ opacity: d, display: 'flex' }}>
                                        <Icon name="check" size={20} color={c.primaryFg} stroke={3} />
                                    </div>
                                </div>
                                <div style={{ fontSize: 26, fontWeight: d > 0.5 ? 600 : 500, color: d > 0.5 ? c.fg : c.mutedFg }}>{s}</div>
                            </div>
                        );
                    })}
                    <div style={{ marginTop: 22, borderRadius: radius.control, background: c.accent, border: `1px solid ${c.border}`, padding: '14px 16px', fontSize: 24, lineHeight: 1.3, opacity: msg, transform: `translateY(${(1 - msg) * 14}px)` }}>Recibimos su queja y la estamos revisando.</div>
                </div>
            </Phone>
            {Array.from({ length: 8 }, (_, k) => (
                <FlyDot key={k} f={f} start={CLICK + 8 + k * 3} len={30} from={{ x: 820, y: 640 } satisfies Pt} to={{ x: 1480, y: 620 }} lift={70} size={16} />
            ))}
            <Cursor f={f} path={[{ at: audioAt + 34, x: 700, y: 760 }, { at: CLICK - 2, x: 270, y: 726 }]} clicks={[CLICK]} />
        </>
    );
};
