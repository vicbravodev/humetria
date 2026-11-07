import { useCurrentFrame } from 'remotion';
import { CardHeader, HeaderRule, Humi, Icon, MainCard } from '../components';
import { easeInOut, enter, exit, lerp, tween } from '../ease';
import { CARD, CARD_PAD, HEADER_H, T, c, floatShadow, radius } from '../theme';
import { CalmContent } from './LoadPeople';

const ITEMS = [
    'Política de prevención',
    'Acuses de lectura',
    'Informe del numeral 7.7',
    'Lista de verificación NOM-037',
    'Evaluación Ley Silla',
] as const;

const ROW = 84;
const tickAt = (k: number): number => T.s5 + 42 + k * 12;
const HUMI_AT = tickAt(ITEMS.length - 1) + 12;
const HUMI_W = 220;
const HUMI_PEEK = 196;

export const Dossier = () => {
    const f = useCurrentFrame();
    if (f < T.s5 - 4) {
        return null;
    }
    const back = T.back;
    const done = ITEMS.filter((_, k) => f >= tickAt(k) + 4).length;
    // Vuelta al reposo: burbuja, Humi, renglones, encabezado y zona de carga, en cascada.
    const bubbleOut = back - 34;
    const humiDown = back - 28;
    const rowsOut = back - 22;
    const headerOut = back - 16;
    const calmIn = back - 8;
    const humiUp = tween(f, HUMI_AT, 22) * (1 - tween(f, humiDown, 22, easeInOut));

    return (
        <MainCard style={enter(f, T.s5 - 4, { dur: 28, y: 18, scale: 0.96 })}>
            {/* Encabezado del expediente; al volver cede el lugar al de reposo. */}
            <CardHeader
                title="Expediente · Planta Querétaro"
                subtitle={`${done} de ${ITEMS.length} documentos listos`}
                style={exit(f, headerOut, { dur: 12, y: -8, scale: 1 })}
            />
            <HeaderRule />
            {f >= calmIn ? (
                <CalmContent
                    subtitle="0 personas"
                    headerStyle={enter(f, calmIn, { dur: 18, y: 8, scale: 1 })}
                    dropzone={{ highlight: 0, style: enter(f, calmIn + 6, { dur: 22, y: 0, scale: 0.98 }) }}
                />
            ) : null}

            {ITEMS.map((item, k) => {
                const t = tween(f, tickAt(k), 8, easeInOut);
                const rowStyle = f >= rowsOut ? exit(f, rowsOut + k * 2, { dur: 12, y: -8, scale: 1 }) : enter(f, T.s5 + 10 + k * 2, { dur: 20, y: 14, scale: 1 });
                return (
                    <div
                        key={item}
                        style={{
                            position: 'absolute',
                            left: CARD_PAD,
                            top: HEADER_H + 24 + k * ROW,
                            height: ROW,
                            display: 'flex',
                            alignItems: 'center',
                            gap: 20,
                            ...rowStyle,
                        }}
                    >
                        <div
                            style={{
                                position: 'relative',
                                width: 44,
                                height: 44,
                                borderRadius: 99,
                                border: `2px solid ${c.track}`,
                                boxSizing: 'border-box',
                            }}
                        >
                            <div
                                style={{
                                    position: 'absolute',
                                    inset: -2,
                                    borderRadius: 99,
                                    background: c.primary,
                                    opacity: t,
                                    transform: `scale(${lerp(0.94, 1, t)})`,
                                }}
                            />
                            <div style={{ position: 'absolute', inset: -2, clipPath: `inset(0 ${100 - 100 * tween(f, tickAt(k) + 3, 8)}% 0 0)` }}>
                                <Icon name="check" size={44} color={c.primaryFg} stroke={2.6} style={{ padding: 9, boxSizing: 'border-box' }} />
                            </div>
                        </div>
                        <div style={{ fontSize: 31, fontWeight: 500, color: t > 0.5 ? c.fg : c.mutedFg }}>{item}</div>
                    </div>
                );
            })}

            <div
                style={{
                    position: 'absolute',
                    right: 48 + HUMI_W + 16,
                    top: CARD.h - 130,
                    padding: '0 24px',
                    height: 72,
                    display: 'flex',
                    alignItems: 'center',
                    borderRadius: radius.card,
                    background: c.secondary,
                    border: `1px solid ${c.border}`,
                    boxShadow: floatShadow,
                    fontSize: 30,
                    fontWeight: 500,
                    color: c.fg,
                    whiteSpace: 'nowrap',
                    transformOrigin: 'right center',
                    ...(f >= bubbleOut ? exit(f, bubbleOut, { dur: 12, y: 8 }) : enter(f, HUMI_AT + 14, { dur: 18, y: 10 })),
                }}
            >
                Todo listo para la inspección.
            </div>

            <div
                style={{
                    position: 'absolute',
                    right: 48,
                    top: CARD.h - HUMI_PEEK,
                    transform: `translateY(${lerp(HUMI_PEEK + 30, 0, humiUp)}px)`,
                }}
            >
                <Humi width={HUMI_W} />
            </div>
        </MainCard>
    );
};
