import type { CSSProperties } from 'react';
import { Img, staticFile, useCurrentFrame } from 'remotion';
import { CheckBadge, Icon, PHONE, Phone } from '../components';
import { easeInOut, enter, exit, lerp, life, tween } from '../ease';
import { CARD, ROW_H, T, c, floatShadow, radius } from '../theme';
import { PEOPLE, ROWS_TOP, rowExitAt } from './LoadPeople';

/** Centro del teléfono grande y de su versión mini (escena 3). */
export const PHONE_AT = { x: 880, y: 480 } as const;
export const MINI = { x: 200, scale: 0.3 } as const;
export const MORPH = { at: T.s3, dur: 36 } as const;

const TAP_OPEN = 296;
const TAP_ANSWER = 352;
const SENT = 386;

const Touch = ({ f, at }: { f: number; at: number }) => {
    const show = tween(f, at - 8, 8);
    const press = tween(f, at, 4, easeInOut);
    const gone = tween(f, at + 4, 12, easeInOut);
    if (f < at - 8 || gone >= 1) {
        return null;
    }
    return (
        <div
            style={{
                position: 'absolute',
                left: '50%',
                top: '50%',
                width: 60,
                height: 60,
                marginLeft: -30,
                marginTop: -30,
                borderRadius: 999,
                background: 'rgba(241,250,248,0.16)',
                border: '2px solid rgba(241,250,248,0.55)',
                opacity: show * (1 - gone),
                transform: `scale(${lerp(1.08, 1, show) * lerp(1, 0.86, press) * lerp(1, 1.25, gone)})`,
            }}
        />
    );
};

const LockScreen = ({ f }: { f: number }) => {
    const out = exit(f, TAP_OPEN + 6, { dur: 10, y: -16, scale: 0.98 });
    return (
        <div style={{ position: 'absolute', inset: 0, ...out }}>
            <div
                style={{
                    position: 'absolute',
                    top: 70,
                    width: '100%',
                    textAlign: 'center',
                    fontSize: 84,
                    fontWeight: 500,
                    color: c.fg,
                    letterSpacing: -2,
                }}
            >
                9:41
            </div>
            <div style={{ position: 'absolute', top: 180, width: '100%', textAlign: 'center', fontSize: 26, color: c.mutedFg }}>
                jueves 6 de noviembre
            </div>
            <div
                style={{
                    position: 'absolute',
                    left: 14,
                    right: 14,
                    top: 250,
                    padding: 20,
                    borderRadius: radius.card,
                    background: c.secondary,
                    border: `1px solid ${c.border}`,
                    boxShadow: floatShadow,
                    ...enter(f, 248, { dur: 18, y: -24 }),
                }}
            >
                <div style={{ display: 'flex', alignItems: 'center', gap: 10, fontSize: 24, color: c.mutedFg }}>
                    <Img src={staticFile('brand/humetria-simbolo-color-oscuro.svg')} style={{ width: 30, height: 30 }} />
                    <span style={{ fontWeight: 500 }}>Humetría</span>
                    <span style={{ marginLeft: 'auto' }}>ahora</span>
                </div>
                <div style={{ marginTop: 14, fontSize: 30, fontWeight: 600, lineHeight: 1.2, color: c.fg }}>
                    Tu encuesta de Humetría
                </div>
                <div style={{ marginTop: 6, fontSize: 26, color: c.mutedFg }}>Enlace de un solo uso</div>
                <div
                    style={{
                        position: 'relative',
                        marginTop: 18,
                        height: 56,
                        borderRadius: radius.control,
                        background: c.primary,
                        color: c.primaryFg,
                        fontSize: 28,
                        fontWeight: 600,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        transform: `scale(${lerp(1, 0.97, tween(f, TAP_OPEN, 4) * (1 - tween(f, TAP_OPEN + 4, 8)))})`,
                    }}
                >
                    Contestar
                    <Touch f={f} at={TAP_OPEN} />
                </div>
            </div>
        </div>
    );
};

const OPTIONS = ['Siempre', 'Casi siempre', 'Algunas veces', 'Casi nunca', 'Nunca'] as const;

const Survey = ({ f }: { f: number }) => {
    if (f < TAP_OPEN + 10) {
        return null;
    }
    const sel = tween(f, TAP_ANSWER, 8);
    const progress = lerp(0.9, 1, tween(f, TAP_ANSWER + 10, 18, easeInOut));
    return (
        <div style={{ position: 'absolute', inset: 0, ...life(f, TAP_OPEN + 16, SENT, { dur: 20, x: 36, y: 0, scale: 1, outDur: 12 }) }}>
            <div
                style={{
                    position: 'absolute',
                    top: 66,
                    left: 24,
                    display: 'flex',
                    alignItems: 'center',
                    gap: 10,
                    fontSize: 26,
                    color: c.mutedFg,
                }}
            >
                <Icon name="shield" size={26} color={c.primary} />
                Encuesta confidencial
            </div>
            <div style={{ position: 'absolute', top: 116, left: 24, right: 24, height: 6, borderRadius: 99, background: c.track }}>
                <div
                    style={{
                        width: '100%',
                        height: '100%',
                        borderRadius: 99,
                        background: c.primary,
                        transformOrigin: 'left',
                        transform: `scaleX(${progress})`,
                    }}
                />
            </div>
            <div
                style={{
                    position: 'absolute',
                    top: 148,
                    left: 24,
                    right: 24,
                    fontSize: 31,
                    fontWeight: 600,
                    lineHeight: 1.25,
                    color: c.fg,
                }}
            >
                Mi trabajo me exige hacer mucho esfuerzo físico
            </div>
            <div style={{ position: 'absolute', top: 300, left: 18, right: 18, display: 'flex', flexDirection: 'column', gap: 10 }}>
                {OPTIONS.map((o, i) => {
                    const on = i === 2 ? sel : 0;
                    return (
                        <div
                            key={o}
                            style={{
                                position: 'relative',
                                height: 58,
                                borderRadius: radius.control,
                                border: `1px solid ${c.border}`,
                                display: 'flex',
                                alignItems: 'center',
                                gap: 14,
                                padding: '0 18px',
                                fontSize: 27,
                                color: c.fg,
                                ...enter(f, TAP_OPEN + 20 + i * 2, { dur: 16, y: 10, scale: 1 }),
                            }}
                        >
                            <div
                                style={{
                                    position: 'absolute',
                                    inset: -1,
                                    borderRadius: radius.control,
                                    border: `2px solid ${c.primary}`,
                                    background: c.accent,
                                    opacity: on,
                                }}
                            />
                            <div
                                style={{
                                    position: 'relative',
                                    width: 24,
                                    height: 24,
                                    borderRadius: 99,
                                    border: `2px solid ${c.mutedFg}`,
                                    boxSizing: 'border-box',
                                }}
                            >
                                <div
                                    style={{
                                        position: 'absolute',
                                        inset: -2,
                                        borderRadius: 99,
                                        background: c.primary,
                                        opacity: on,
                                    }}
                                />
                                <div
                                    style={{
                                        position: 'absolute',
                                        inset: 5,
                                        borderRadius: 99,
                                        background: c.primaryFg,
                                        opacity: on,
                                        transform: `scale(${lerp(0.4, 1, on)})`,
                                    }}
                                />
                            </div>
                            <span style={{ position: 'relative' }}>{o}</span>
                            {i === 2 ? <Touch f={f} at={TAP_ANSWER} /> : null}
                        </div>
                    );
                })}
            </div>
        </div>
    );
};

/** Pantalla de "enviada". En la escena 3 se reusa sin texto en los teléfonos mini. */
export const SuccessScreen = ({ inStyle, textOpacity, checkScale }: { inStyle?: CSSProperties; textOpacity: number; checkScale: number }) => (
    <div style={{ position: 'absolute', inset: 0, ...inStyle }}>
        <CheckBadge
            size={96}
            style={{ position: 'absolute', left: 170 - 48, top: 310 - 48, transform: `scale(${checkScale})` }}
        />
        <div style={{ position: 'absolute', top: 390, left: 20, right: 20, textAlign: 'center', opacity: textOpacity }}>
            <div style={{ fontSize: 32, fontWeight: 600, color: c.fg }}>Respuesta enviada</div>
            <div style={{ marginTop: 10, fontSize: 26, lineHeight: 1.3, color: c.mutedFg }}>Gracias. Su respuesta es anónima.</div>
        </div>
    </div>
);

const Envelope = ({ f, i }: { f: number; i: number }) => {
    const start = rowExitAt(i) + 2;
    const t = tween(f, start + 4, 30, easeInOut);
    const appear = tween(f, start, 8);
    const arrive = tween(f, start + 26, 8, easeInOut);
    if (f < start || arrive >= 1) {
        return null;
    }
    const x0 = CARD.x + 32 + 26;
    const y0 = CARD.y + ROWS_TOP + i * ROW_H + ROW_H / 2;
    const x2 = PHONE_AT.x;
    const y2 = PHONE_AT.y - 40;
    const x1 = (x0 + x2) / 2;
    const y1 = Math.min(y0, y2) - 200;
    const x = (1 - t) ** 2 * x0 + 2 * (1 - t) * t * x1 + t ** 2 * x2;
    const y = (1 - t) ** 2 * y0 + 2 * (1 - t) * t * y1 + t ** 2 * y2;
    return (
        <div
            style={{
                position: 'absolute',
                left: x - 36,
                top: y - 26,
                width: 72,
                height: 52,
                borderRadius: radius.control,
                background: c.secondary,
                border: `1px solid ${c.border}`,
                boxShadow: floatShadow,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                opacity: appear * (1 - arrive),
                transform: `scale(${lerp(0.94, 1, appear) * lerp(1, 0.7, arrive)})`,
                filter: `blur(${lerp(4, 0, appear)}px)`,
            }}
        >
            <Icon name="mail" size={30} color={c.primary} />
        </div>
    );
};

export const Invite = () => {
    const f = useCurrentFrame();
    if (f < T.s2 || f > MORPH.at + MORPH.dur) {
        return null;
    }
    const sent = Math.round(120 * tween(f, 240, 34));

    const m = tween(f, MORPH.at, MORPH.dur, easeInOut);
    const phoneIn = enter(f, T.s2 + 18, { dur: 26, x: 90, y: 0, scale: 0.96 });
    const phoneStyle =
        f < MORPH.at
            ? phoneIn
            : {
                  transform: `translate(${lerp(0, MINI.x - PHONE_AT.x, m)}px, 0px) scale(${lerp(1, MINI.scale, m)})`,
              };

    return (
        <>

            <div style={{ position: 'absolute', left: 96, top: 296, ...life(f, 236, MORPH.at, { dur: 20, x: -24, y: 0, outDur: 16 }) }}>
                <div style={{ fontSize: 132, fontWeight: 600, letterSpacing: -4, lineHeight: 1, color: c.fg }}>{sent}</div>
                <div style={{ marginTop: 12, fontSize: 34, color: c.mutedFg }}>invitaciones enviadas</div>
            </div>

            <div
                style={{
                    position: 'absolute',
                    left: 96,
                    top: 530,
                    width: 500,
                    padding: 24,
                    boxSizing: 'border-box',
                    display: 'flex',
                    alignItems: 'center',
                    gap: 18,
                    borderRadius: radius.card,
                    background: c.card,
                    border: `1px solid ${c.border}`,
                    ...life(f, 268, MORPH.at + 3, { dur: 20, y: 14, outDur: 16 }),
                }}
            >
                <div
                    style={{
                        width: 60,
                        height: 60,
                        borderRadius: radius.control,
                        background: c.accent,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                    }}
                >
                    <Icon name="bell" size={30} color={c.primary} />
                </div>
                <div>
                    <div style={{ fontSize: 30, fontWeight: 600, color: c.fg }}>Recordatorio automático</div>
                    <div style={{ marginTop: 4, fontSize: 26, color: c.mutedFg }}>a quien aún no contesta</div>
                </div>
            </div>

            <Phone
                style={{
                    left: PHONE_AT.x - PHONE.w / 2,
                    top: PHONE_AT.y - PHONE.h / 2,
                    ...phoneStyle,
                }}
            >
                <LockScreen f={f} />
                <Survey f={f} />
                {f >= SENT ? (
                    <SuccessScreen
                        inStyle={enter(f, SENT + 6, { dur: 18, y: 10 })}
                        textOpacity={1 - tween(f, MORPH.at, 12, easeInOut)}
                        checkScale={lerp(1, 2.2, tween(f, MORPH.at, MORPH.dur - 4, easeInOut))}
                    />
                ) : null}
            </Phone>
            {PEOPLE.map((p, i) => (
                <Envelope key={p.name} f={f} i={i} />
            ))}
        </>
    );
};
